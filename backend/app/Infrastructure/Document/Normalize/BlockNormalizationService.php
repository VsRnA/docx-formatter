<?php

namespace App\Infrastructure\Document\Normalize;

use App\Domain\Document\Entity\DocumentBlock as DomainBlock;
use App\Domain\Document\ValueObject\TranslationStatus;
use App\Domain\Docx\Port\BlockNormalizerPort;
use App\Domain\Document\Port\HtmlRendererPort;
use App\Domain\Docx\ValueObject\BlockType as DomainBlockType;
use App\Enums\BlockType;
use App\Models\Document;
use App\Models\DocumentBlock;
use Illuminate\Support\Facades\Log;
use Throwable;

final class BlockNormalizationService
{
    public function __construct(
        private readonly BlockNormalizerPort $normalizer,
        private readonly HtmlRendererPort $renderer,
    ) {}

    /**
     * @return array{normalized: int, skipped: int}
     */
    public function normalizeDocument(Document $document, int $limit): array
    {
        $candidates = $this->selectCandidates($document);
        $normalized = 0;
        $skipped = 0;

        foreach (array_slice($candidates, 0, $limit) as $block) {
            if ($this->normalizeBlock($block)) {
                $normalized++;
            } else {
                $skipped++;
            }
        }

        return ['normalized' => $normalized, 'skipped' => $skipped];
    }

    /**
     * @return list<DocumentBlock>
     */
    private function selectCandidates(Document $document): array
    {
        $coverageFails = ($document->meta_json['parse_coverage']['passes_threshold'] ?? true) === false;

        return $document->blocks()
            ->orderBy('sort')
            ->get()
            ->filter(function (DocumentBlock $block) use ($coverageFails): bool {
                $meta = $block->meta_json ?? [];

                if ($block->type === BlockType::HtmlRaw) {
                    return true;
                }

                if ($meta['needs_review'] ?? false) {
                    return true;
                }

                if (($meta['confidence'] ?? 1) < 0.5) {
                    return true;
                }

                return $coverageFails && ($meta['source'] ?? '') === 'ooxml_fallback';
            })
            ->values()
            ->all();
    }

    private function normalizeBlock(DocumentBlock $block): bool
    {
        $meta = $block->meta_json ?? [];
        $fragment = (string) ($meta['ooxml_fragment'] ?? '');
        $plain = $block->text_original;

        try {
            $ir = $this->normalizer->normalize($fragment, $plain);
        } catch (Throwable $e) {
            Log::warning('Block normalization failed', [
                'block_id' => $block->id,
                'message' => $e->getMessage(),
            ]);

            return false;
        }

        if ($ir === null) {
            return false;
        }

        $type = $this->blockTypeFromKind($ir['kind']);
        $textOriginal = $this->plainFromChildren($ir['children']);
        $meta['needs_review'] = false;
        $meta['confidence'] = 0.85;
        $meta['ai_normalized'] = true;
        unset($meta['ooxml_fragment']);

        $preview = new DomainBlock(
            id: (string) $block->id,
            type: DomainBlockType::from($type->value),
            sort: (int) $block->sort,
            html: $block->html,
            textOriginal: $textOriginal,
            textTranslated: $block->text_translated,
            translationStatus: TranslationStatus::from($block->translation_status->value),
            styles: $block->styles_json,
            meta: $meta,
            assets: $block->assets_json,
            contentJson: $ir,
        );

        $html = $this->renderer->renderBlock($preview) ?? $block->html;

        $block->fill([
            'type' => $type,
            'content_json' => $ir,
            'text_original' => $textOriginal,
            'html' => $html,
            'meta_json' => $meta,
        ]);

        if ($block->exists) {
            $block->save();
        }

        return true;
    }

    private function blockTypeFromKind(string $kind): BlockType
    {
        return match ($kind) {
            'heading' => BlockType::Heading,
            'list' => BlockType::List,
            'caption' => BlockType::Caption,
            default => BlockType::Paragraph,
        };
    }

    /**
     * @param  list<array{kind: string, text: string}>  $children
     */
    private function plainFromChildren(array $children): ?string
    {
        $parts = [];
        foreach ($children as $child) {
            $text = trim((string) ($child['text'] ?? ''));
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return $parts !== [] ? implode(' ', $parts) : null;
    }
}

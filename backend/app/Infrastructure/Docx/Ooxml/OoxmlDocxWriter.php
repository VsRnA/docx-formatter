<?php

namespace App\Infrastructure\Docx\Ooxml;

use App\Enums\BlockType;
use App\Infrastructure\Docx\Ooxml\Writing\OoxmlPackageWriter;
use App\Infrastructure\Docx\Ooxml\Writing\OoxmlTextReplacer;
use App\Infrastructure\Docx\Ooxml\Writing\OoxmlTextScopeWalker;
use App\Models\Document;
use App\Models\DocumentBlock;
use DOMElement;

final class OoxmlDocxWriter
{
    public function __construct(
        private readonly OoxmlTextScopeWalker $scopeWalker,
        private readonly OoxmlTextReplacer $textReplacer,
        private readonly OoxmlPackageWriter $packageWriter,
    ) {}

    public function documentNeedsPatch(Document $document): bool
    {
        foreach ($document->blocks as $block) {
            if ($this->blockHasTranslatedText($block) || $this->blockWasEditedInEditor($block)) {
                return true;
            }
        }

        return false;
    }

    public function writeFromDocument(Document $document, string $sourcePath, string $outputPath): array
    {
        $package = new OoxmlPackage($sourcePath);

        try {
            $dom = $package->document();
            $scopes = $this->scopeWalker->collect($dom);
            $blocks = $document->blocks->all();
            $textsByScope = $this->resolveTextsByScope($blocks);
            $updated = 0;
            $skipped = 0;

            $blocksByScope = $this->indexBlocksByScope($blocks);

            $segmentDataByScope = $this->resolveSegmentDataByScope($blocks);

            foreach ($scopes as $scope) {
                $block = $blocksByScope[$scope['index']] ?? null;
                $text = $textsByScope[$scope['index']] ?? null;
                $segmentData = $segmentDataByScope[$scope['index']] ?? null;

                $applied = match ($scope['kind']) {
                    'paragraph' => $this->applyParagraphScope($scope['element'], $segmentData, $text),
                    'table' => $this->applyTableScope($scope['element'], $block, $text, $segmentData),
                };

                if ($applied) {
                    $updated++;
                } else {
                    $skipped++;
                }
            }

            $this->packageWriter->saveWithDocumentXml($sourcePath, $dom, $outputPath);

            return [
                'scopes_updated' => $updated,
                'scopes_skipped' => $skipped,
            ];
        } finally {
            $package->close();
        }
    }

    /**
     * @param  list<DocumentBlock>  $blocks
     * @return array<int, string>
     */
    private function resolveTextsByScope(array $blocks): array
    {
        $byScope = [];

        foreach ($blocks as $block) {
            if (! $this->isWritableBlock($block)) {
                continue;
            }

            $scopeIndex = $block->meta_json['ooxml_scope_index'] ?? null;
            if (! is_int($scopeIndex)) {
                continue;
            }

            $text = $this->resolveBlockText($block);
            if ($text === null || $text === '') {
                continue;
            }

            if (
                ! isset($byScope[$scopeIndex])
                || $this->shouldPreferText($text, $byScope[$scopeIndex], $block)
            ) {
                $byScope[$scopeIndex] = $text;
            }
        }

        if ($byScope === [] && $this->blocksHaveTranslations($blocks)) {
            $byScope = $this->resolveTextsByPosition($blocks);
        }

        return $byScope;
    }

    /**
     * Legacy fallback: documents translated before ooxml_scope_index existed.
     *
     * @param  list<DocumentBlock>  $blocks
     * @return array<int, string>
     */
    private function resolveTextsByPosition(array $blocks): array
    {
        $texts = [];
        $position = 0;

        foreach ($blocks as $block) {
            if (! $this->isWritableBlock($block)) {
                continue;
            }

            $text = $this->resolveBlockText($block);
            if ($text === null || $text === '') {
                continue;
            }

            $texts[$position++] = $text;
        }

        return $texts;
    }

    /**
     * @param  list<DocumentBlock>  $blocks
     */
    private function blocksHaveTranslations(array $blocks): bool
    {
        foreach ($blocks as $block) {
            if (is_string($block->text_translated) && trim($block->text_translated) !== '') {
                return true;
            }
        }

        return false;
    }

    private function isWritableBlock(DocumentBlock $block): bool
    {
        return match ($block->type) {
            BlockType::Paragraph,
            BlockType::Heading,
            BlockType::List,
            BlockType::Table => true,
            default => false,
        };
    }

    private function blockNeedsPatch(DocumentBlock $block): bool
    {
        return $this->resolveBlockText($block) !== null;
    }

    private function blockHasTranslatedText(DocumentBlock $block): bool
    {
        return is_string($block->text_translated) && trim($block->text_translated) !== '';
    }

    private function blockWasEditedInEditor(DocumentBlock $block): bool
    {
        return ($block->meta_json['content_edited'] ?? false) === true;
    }

    private function resolveBlockText(DocumentBlock $block): ?string
    {
        if ($this->blockHasTranslatedText($block)) {
            return trim((string) $block->text_translated);
        }

        if ($this->blockWasEditedInEditor($block)) {
            return $this->plainTextFromHtml($block);
        }

        return null;
    }

    private function plainTextFromHtml(DocumentBlock $block): ?string
    {
        $plain = trim(html_entity_decode(strip_tags($block->html ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $plain !== '' ? $plain : null;
    }

    private function textMatchesScope(DOMElement $scope, string $text): bool
    {
        return $this->normalizeText(OoxmlXml::text($scope)) === $this->normalizeText($text);
    }

    private function normalizeText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function shouldPreferText(string $candidate, string $current, DocumentBlock $block): bool
    {
        if ($block->text_translated) {
            return true;
        }

        return mb_strlen($candidate) > mb_strlen($current);
    }

    /**
     * @param  array{segments: list<array{id: int, text: string, t_indices: list<int>, translatable?: bool}>, translations: array<int, string>}|null  $segmentData
     */
    private function applyParagraphScope(DOMElement $paragraph, ?array $segmentData, ?string $text): bool
    {
        if (
            is_array($segmentData)
            && ($segmentData['segments'] ?? []) !== []
            && ($segmentData['translations'] ?? []) !== []
        ) {
            return $this->textReplacer->replaceSegments(
                $paragraph,
                $segmentData['segments'],
                $segmentData['translations'],
            );
        }

        return $text !== null && $text !== ''
            && ! $this->textMatchesScope($paragraph, $text)
            ? $this->textReplacer->replaceInParagraph($paragraph, $text)
            : false;
    }

    /**
     * @param  array{segments: list<array{id: int, text: string, t_indices: list<int>, translatable?: bool}>, translations: array<int, string>, table_cells?: list<array{cell_index: int, segments: list<array{id: int, text: string, t_indices: list<int>, translatable?: bool}>}>}|null  $segmentData
     */
    private function applyTableScope(DOMElement $table, ?DocumentBlock $block, ?string $text, ?array $segmentData): bool
    {
        $tableCells = is_array($segmentData) ? ($segmentData['table_cells'] ?? null) : null;
        $translations = is_array($segmentData) ? ($segmentData['translations'] ?? []) : [];

        if (is_array($tableCells) && $tableCells !== [] && $translations !== []) {
            $cells = $this->tableCells($table);
            $updated = 0;

            foreach ($tableCells as $cellData) {
                $cellIndex = (int) ($cellData['cell_index'] ?? -1);
                $segments = $cellData['segments'] ?? [];
                if (! isset($cells[$cellIndex]) || $segments === []) {
                    continue;
                }

                if ($this->textReplacer->replaceSegments($cells[$cellIndex], $segments, $translations)) {
                    $updated++;
                }
            }

            return $updated > 0;
        }

        if ($block !== null && $this->blockNeedsPatch($block)) {
            $cellTexts = $this->extractTableCellTextsFromHtml($block->html ?? '');
            if ($cellTexts !== []) {
                return $this->textReplacer->replaceInTable($table, $cellTexts) > 0;
            }
        }

        if ($text === null || $text === '') {
            return false;
        }

        if (str_contains($text, "\n")) {
            $rows = array_map(
                static fn (string $row): array => array_map('trim', explode(' | ', $row)),
                array_filter(explode("\n", $text), static fn (string $row): bool => trim($row) !== ''),
            );
            $cellTexts = array_merge(...($rows !== [] ? $rows : [[]]));

            return $this->textReplacer->replaceInTable($table, $cellTexts) > 0;
        }

        return false;
    }

    /**
     * @param  list<DocumentBlock>  $blocks
     * @return array<int, array{
     *     segments: list<array{id: int, text: string, t_indices: list<int>, translatable?: bool}>,
     *     translations: array<int, string>,
     *     table_cells?: list<array{cell_index: int, segments: list<array{id: int, text: string, t_indices: list<int>, translatable?: bool}>}>
     * }>
     */
    private function resolveSegmentDataByScope(array $blocks): array
    {
        $byScope = [];

        foreach ($blocks as $block) {
            $scopeIndex = $block->meta_json['ooxml_scope_index'] ?? null;
            if (! is_int($scopeIndex)) {
                continue;
            }

            $segments = $block->meta_json['ooxml_segments'] ?? [];
            $translations = $block->meta_json['ooxml_segment_translations'] ?? [];
            $tableCells = $block->meta_json['ooxml_table_cells'] ?? null;

            if (! is_array($segments)) {
                $segments = [];
            }

            if (! is_array($translations)) {
                $translations = [];
            }

            if (! isset($byScope[$scopeIndex])) {
                $byScope[$scopeIndex] = [
                    'segments' => [],
                    'translations' => [],
                ];
            }

            foreach ($segments as $segment) {
                if (! is_array($segment)) {
                    continue;
                }

                $segmentId = (int) ($segment['id'] ?? -1);
                $byScope[$scopeIndex]['segments'][$segmentId] = $segment;
            }

            $byScope[$scopeIndex]['translations'] = array_merge(
                $byScope[$scopeIndex]['translations'],
                $translations,
            );

            if (is_array($tableCells) && $tableCells !== []) {
                $byScope[$scopeIndex]['table_cells'] = $tableCells;
            }
        }

        foreach ($byScope as $scopeIndex => $data) {
            $byScope[$scopeIndex]['segments'] = array_values($data['segments']);
        }

        return $byScope;
    }

    /**
     * @return list<DOMElement>
     */
    private function tableCells(DOMElement $table): array
    {
        $cells = [];
        foreach (OoxmlXml::children($table, 'tr') as $row) {
            foreach (OoxmlXml::children($row, 'tc') as $cell) {
                $cells[] = $cell;
            }
        }

        return $cells;
    }

    /**
     * @param  list<DocumentBlock>  $blocks
     * @return array<int, DocumentBlock>
     */
    private function indexBlocksByScope(array $blocks): array
    {
        $indexed = [];

        foreach ($blocks as $block) {
            $scopeIndex = $block->meta_json['ooxml_scope_index'] ?? null;
            if (! is_int($scopeIndex)) {
                continue;
            }

            if (! isset($indexed[$scopeIndex]) || $block->type === BlockType::Table) {
                $indexed[$scopeIndex] = $block;
            }
        }

        return $indexed;
    }

    /**
     * @return list<string>
     */
    private function extractTableCellTextsFromHtml(string $html): array
    {
        if (! preg_match_all('/<t[dh][^>]*>(.*?)<\/t[dh]>/si', $html, $matches)) {
            return [];
        }

        return array_map(
            static fn (string $cell): string => trim(html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            $matches[1],
        );
    }
}

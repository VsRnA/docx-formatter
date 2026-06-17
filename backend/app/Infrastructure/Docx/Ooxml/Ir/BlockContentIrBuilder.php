<?php

namespace App\Infrastructure\Docx\Ooxml\Ir;

use App\Domain\Docx\Entity\ParsedBlock;
use App\Domain\Docx\ValueObject\BlockType;

final class BlockContentIrBuilder
{
    /**
     * @return array<string, mixed>|null
     */
    public function fromParsedBlock(ParsedBlock $block): ?array
    {
        if ($block->contentJson !== null) {
            return $block->contentJson;
        }

        return match ($block->type) {
            BlockType::Heading,
            BlockType::Paragraph,
            BlockType::List,
            BlockType::Caption,
            BlockType::ImageText,
            BlockType::LinkBlock => $this->textBlockIr($block),
            BlockType::Table => $this->tableIr($block),
            BlockType::Image => $this->imageIr($block),
            BlockType::Formula => $this->formulaIr($block),
            BlockType::HtmlRaw => [
                'kind' => 'ooxml_fallback',
                'text' => $block->textOriginal,
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function textBlockIr(ParsedBlock $block): array
    {
        $segments = $block->meta['ooxml_segments'] ?? null;
        if (is_array($segments) && $segments !== []) {
            return [
                'kind' => $block->type->value,
                'children' => array_map(static fn (array $segment): array => [
                    'kind' => 'text',
                    'segmentId' => $segment['id'] ?? null,
                    'text' => $segment['text'] ?? '',
                ], $segments),
            ];
        }

        return [
            'kind' => $block->type->value,
            'children' => $block->textOriginal
                ? [['kind' => 'text', 'text' => $block->textOriginal]]
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tableIr(ParsedBlock $block): array
    {
        return [
            'kind' => 'table',
            'rows' => (int) ($block->meta['rows'] ?? 0),
            'cols' => (int) ($block->meta['cols'] ?? 0),
            'cells' => $block->meta['ooxml_table_cells'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formulaIr(ParsedBlock $block): array
    {
        return [
            'kind' => 'formula',
            'text' => $block->contentJson['text'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function imageIr(ParsedBlock $block): array
    {
        return [
            'kind' => 'image',
            'relationshipId' => $block->assets['relationship_id'] ?? null,
            'attributes' => $block->meta['image'] ?? null,
        ];
    }
}

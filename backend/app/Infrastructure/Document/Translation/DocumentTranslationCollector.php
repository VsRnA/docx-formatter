<?php

namespace App\Infrastructure\Document\Translation;

use App\Domain\Docx\Entity\ParsedBlock;
use App\Domain\Docx\Entity\ParsedDocument;

final class DocumentTranslationCollector
{
    /**
     * @return list<array{blockIndex: int, segmentId: int, text: string, translatable: bool}>
     */
    public function collect(ParsedDocument $parsed): array
    {
        $units = [];

        foreach ($parsed->blocks as $blockIndex => $block) {
            $units = array_merge($units, $this->collectFromBlock($blockIndex, $block));
        }

        return $units;
    }

    /**
     * @return list<array{blockIndex: int, segmentId: int, text: string, translatable: bool}>
     */
    private function collectFromBlock(int $blockIndex, ParsedBlock $block): array
    {
        $tableCells = $block->meta['ooxml_table_cells'] ?? null;
        if (is_array($tableCells) && $tableCells !== []) {
            $segments = [];
            foreach ($tableCells as $cell) {
                foreach ($cell['segments'] ?? [] as $segment) {
                    $segments[] = $segment;
                }
            }

            return $this->collectFromSegments($blockIndex, $segments);
        }

        $segmentList = $block->meta['ooxml_segments'] ?? null;
        if (is_array($segmentList) && $segmentList !== []) {
            return $this->collectFromSegments($blockIndex, $segmentList);
        }

        if (! $block->textOriginal) {
            return [];
        }

        return [[
            'blockIndex' => $blockIndex,
            'segmentId' => 0,
            'text' => (string) $block->textOriginal,
            'translatable' => true,
        ]];
    }

    /**
     * @param  list<array{id: int, text: string, translatable?: bool}>  $segments
     * @return list<array{blockIndex: int, segmentId: int, text: string, translatable: bool}>
     */
    private function collectFromSegments(int $blockIndex, array $segments): array
    {
        $units = [];

        foreach ($segments as $segment) {
            $units[] = [
                'blockIndex' => $blockIndex,
                'segmentId' => (int) ($segment['id'] ?? 0),
                'text' => (string) ($segment['text'] ?? ''),
                'translatable' => (bool) ($segment['translatable'] ?? true),
            ];
        }

        return $units;
    }
}

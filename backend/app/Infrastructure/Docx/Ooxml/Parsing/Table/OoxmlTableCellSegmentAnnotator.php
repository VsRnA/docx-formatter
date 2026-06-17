<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing\Table;

use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlHtmlSegmentAnnotator;
use App\Infrastructure\Docx\Ooxml\Writing\OoxmlTextSegmentCollector;
use App\Support\Constants\OoxmlTags;
use DOMElement;

final class OoxmlTableCellSegmentAnnotator
{
    public function __construct(
        private readonly OoxmlTextSegmentCollector $segments,
        private readonly OoxmlHtmlSegmentAnnotator $segmentHtml,
    ) {}

    /**
     * @param  list<DOMElement>  $rows
     * @param  list<list<array{html: string, attrs: string, bold: bool, skip: bool, rowspan: int, colspan: int, vmerge: ?string, attrs_data?: array<string, mixed>, annotated_html?: string}>>  $grid
     * @return list<array{cell_index: int, segments: list<array{id: int, text: string, t_indices: list<int>, translatable: bool}>}>
     */
    public function annotateGrid(array $rows, array &$grid): array
    {
        $tableCells = [];
        $segmentId = 0;
        $cellIndex = 0;

        foreach ($rows as $rowIndex => $row) {
            $columnIndex = 0;
            foreach (OoxmlXml::children($row, OoxmlTags::TABLE_CELL) as $cellElement) {
                if (($grid[$rowIndex][$columnIndex]['skip'] ?? false) === true) {
                    $columnIndex++;

                    continue;
                }

                $segments = [];
                foreach ($this->collectCellSegments($cellElement) as $segment) {
                    $segments[] = [
                        'id' => $segmentId++,
                        'text' => $segment['text'],
                        't_indices' => $segment['t_indices'],
                        'translatable' => $segment['translatable'],
                    ];
                }

                $annotatedHtml = $this->annotateCellHtml(
                    (string) ($grid[$rowIndex][$columnIndex]['html'] ?? ''),
                    $segments,
                );
                $grid[$rowIndex][$columnIndex]['annotated_html'] = $annotatedHtml;

                if ($segments !== []) {
                    $tableCells[] = [
                        'cell_index' => $cellIndex,
                        'segments' => $segments,
                    ];
                }

                $cellIndex++;
                $columnIndex++;
            }
        }

        return $tableCells;
    }

    /**
     * @return list<array{id: int, text: string, t_indices: list<int>, translatable: bool}>
     */
    private function collectCellSegments(DOMElement $cell): array
    {
        if (! $this->cellHasNestedTable($cell)) {
            return $this->segments->collectFromCell($cell);
        }

        $segments = [];
        $id = 0;

        foreach (OoxmlXml::children($cell, 'p') as $paragraph) {
            foreach ($this->segments->collectFromParagraph($paragraph) as $segment) {
                $segments[] = [
                    'id' => $id++,
                    'text' => $segment['text'],
                    't_indices' => $segment['t_indices'],
                    'translatable' => $segment['translatable'],
                ];
            }
        }

        foreach ($segments as $index => $segment) {
            $segments[$index]['id'] = $index;
        }

        return $segments;
    }

    /**
     * @param  list<array{id: int, text: string, translatable?: bool}>  $segments
     */
    private function annotateCellHtml(string $html, array $segments): string
    {
        if ($segments === [] || ! str_contains($html, 'doc-table--nested')) {
            return $this->segmentHtml->annotate($html, $segments);
        }

        $parts = preg_split(
            '/(<table\b[^>]*\bdoc-table--nested\b[^>]*>.*?<\/table>)/s',
            $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE,
        );

        if (! is_array($parts)) {
            return $this->segmentHtml->annotate($html, $segments);
        }

        $result = '';
        foreach ($parts as $index => $part) {
            if ($index % 2 === 1) {
                $result .= $part;

                continue;
            }

            $result .= $this->segmentHtml->annotate($part, $segments);
        }

        return $result;
    }

    private function cellHasNestedTable(DOMElement $cell): bool
    {
        foreach (OoxmlXml::children($cell, OoxmlTags::TABLE) as $child) {
            return true;
        }

        return false;
    }
}

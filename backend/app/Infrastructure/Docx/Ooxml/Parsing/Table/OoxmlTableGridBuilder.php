<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing\Table;

use App\Infrastructure\Docx\Ooxml\OoxmlPackage;
use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use App\Domain\Docx\ValueObject\ParseContext;
use App\Support\Constants\OoxmlTags;
use DOMElement;

final class OoxmlTableGridBuilder
{
    public function __construct(
        private readonly OoxmlTableCellRenderer $cells,
    ) {}

    /**
     * @param  list<DOMElement>  $rows
     * @param  list<array{marker: string, relationship_id: string, local_path: ?string, attributes: array<string, mixed>}>  $pendingImages
     * @return list<list<array{html: string, attrs: string, bold: bool, skip: bool, rowspan: int, colspan: int, vmerge: ?string}>>
     */
    public function buildGrid(
        array $rows,
        OoxmlPackage $package,
        ParseContext $context,
        array &$pendingImages,
        callable $nestedTableHtml,
    ): array {
        $grid = [];

        foreach ($rows as $row) {
            $gridRow = [];

            foreach (OoxmlXml::children($row, OoxmlTags::TABLE_CELL) as $cell) {
                $gridRow[] = [
                    'html' => $this->cells->renderCell($cell, $package, $context, $pendingImages, $nestedTableHtml),
                    'attrs' => '',
                    'bold' => $this->cells->cellIsBold($cell),
                    'skip' => false,
                    'rowspan' => 1,
                    'colspan' => $this->cells->cellColspan($cell),
                    'vmerge' => $this->cells->cellVMerge($cell),
                    'attrs_data' => $this->cells->cellAttributesData($cell),
                ];
            }

            $grid[] = $gridRow;
        }

        foreach ($grid as $rowIndex => $row) {
            foreach ($row as $cellIndex => $cell) {
                $grid[$rowIndex][$cellIndex]['attrs'] = $this->cells->renderCellAttributes($cell);
            }
        }

        return $grid;
    }

    /**
     * @param  list<list<array{html: string, attrs: string, bold: bool, skip: bool, rowspan: int, colspan: int, vmerge: ?string, attrs_data?: array<string, mixed>}>>  $grid
     */
    public function refreshCellAttributes(array &$grid): void
    {
        foreach ($grid as $rowIndex => $row) {
            foreach ($row as $cellIndex => $cell) {
                $grid[$rowIndex][$cellIndex]['attrs'] = $this->cells->renderCellAttributes($cell);
            }
        }
    }

    /**
     * @param  list<list<array{html: string, attrs: string, bold: bool, skip: bool, rowspan: int, colspan: int, vmerge: ?string, attrs_data?: array<string, mixed>}>>  $grid
     */
    public function applyRowSpans(array &$grid): void
    {
        $columnCount = max(array_map(static fn (array $row): int => count($row), $grid));

        for ($column = 0; $column < $columnCount; $column++) {
            $activeRow = null;

            foreach ($grid as $rowIndex => $row) {
                if (! isset($row[$column])) {
                    continue;
                }

                $vmerge = $row[$column]['vmerge'];

                if ($vmerge === 'continue') {
                    if ($activeRow !== null) {
                        $grid[$activeRow][$column]['rowspan']++;
                        $grid[$rowIndex][$column]['skip'] = true;
                    }

                    continue;
                }

                $activeRow = $rowIndex;
            }
        }
    }

    public function tableStyleAttribute(DOMElement $table): string
    {
        $rules = ['border-collapse:collapse'];

        $width = $this->tableWidthRule($table);
        if ($width !== null) {
            $rules[] = $width;
        }

        return ' style="'.implode(';', $rules).'"';
    }

    /**
     * A table has visible borders only when it (or any cell) declares a
     * non-"none" border inline. Layout tables (the common case in technical
     * manuals) carry no inline borders and must render without rules.
     */
    public function hasVisibleBorders(DOMElement $table): bool
    {
        $tblPr = OoxmlXml::child($table, 'tblPr');
        $tblBorders = $tblPr ? OoxmlXml::child($tblPr, 'tblBorders') : null;
        if ($tblBorders && $this->bordersHaveVisibleEdge($tblBorders)) {
            return true;
        }

        foreach (OoxmlXml::children($table, OoxmlTags::TABLE_ROW) as $row) {
            foreach (OoxmlXml::children($row, OoxmlTags::TABLE_CELL) as $cell) {
                $tcPr = OoxmlXml::child($cell, 'tcPr');
                $tcBorders = $tcPr ? OoxmlXml::child($tcPr, 'tcBorders') : null;
                if ($tcBorders && $this->bordersHaveVisibleEdge($tcBorders)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function colgroupHtml(DOMElement $table): string
    {
        $tblGrid = OoxmlXml::child($table, 'tblGrid');
        if (! $tblGrid) {
            return '';
        }

        $widths = [];
        foreach (OoxmlXml::children($tblGrid, 'gridCol') as $col) {
            $value = OoxmlXml::attr($col, 'w');
            $widths[] = is_numeric($value) ? max(0, (int) $value) : 0;
        }

        $total = array_sum($widths);
        if ($widths === [] || $total <= 0) {
            return '';
        }

        $cols = '';
        foreach ($widths as $width) {
            $cols .= $width > 0
                ? '<col style="width:'.round($width / $total * 100, 2).'%">'
                : '<col>';
        }

        return '<colgroup>'.$cols.'</colgroup>';
    }

    private function tableWidthRule(DOMElement $table): ?string
    {
        $tblPr = OoxmlXml::child($table, 'tblPr');
        $tblW = $tblPr ? OoxmlXml::child($tblPr, 'tblW') : null;
        if (! $tblW) {
            return null;
        }

        $type = OoxmlXml::attr($tblW, 'type');
        $value = OoxmlXml::attr($tblW, 'w');
        if (! is_numeric($value)) {
            return null;
        }

        return match ($type) {
            'pct' => 'width:'.round(((int) $value) / 50, 2).'%',
            'dxa' => 'width:'.OoxmlXml::twipsToPt($value).'pt',
            default => null,
        };
    }

    private function bordersHaveVisibleEdge(DOMElement $borders): bool
    {
        foreach ($borders->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $val = OoxmlXml::attr($child, 'val');
            if ($val !== null && ! in_array($val, ['none', 'nil'], true)) {
                return true;
            }
        }

        return false;
    }
}

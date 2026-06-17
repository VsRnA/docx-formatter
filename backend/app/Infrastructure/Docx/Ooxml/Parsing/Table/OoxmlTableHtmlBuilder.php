<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing\Table;

use App\Infrastructure\Docx\Ooxml\OoxmlPackage;
use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use App\Domain\Docx\ValueObject\ParseContext;
use App\Support\Constants\HtmlCssClasses;
use App\Support\Constants\OoxmlTags;
use DOMElement;

final class OoxmlTableHtmlBuilder
{
    public function __construct(
        private readonly OoxmlTableGridBuilder $gridBuilder,
        private readonly OoxmlTableCellSegmentAnnotator $cellSegments,
    ) {}

    /**
     * @return array{
     *     html: string,
     *     plain: string,
     *     row_count: int,
     *     col_count: int,
     *     table_cells: list<array<string, mixed>>,
     *     pending_images: list<array<string, mixed>>
     * }|null
     */
    public function build(
        DOMElement $table,
        OoxmlPackage $package,
        ParseContext $context,
        string $tableClass,
    ): ?array {
        $rows = OoxmlXml::children($table, OoxmlTags::TABLE_ROW);
        if ($rows === []) {
            return null;
        }

        $pendingImages = [];
        $nestedTableHtml = function (DOMElement $nested) use ($package, $context, &$pendingImages): string {
            $built = $this->build($nested, $package, $context, HtmlCssClasses::DOC_TABLE_NESTED);
            if ($built !== null && ($built['pending_images'] ?? []) !== []) {
                array_push($pendingImages, ...$built['pending_images']);
            }

            return $built['html'] ?? '';
        };

        $grid = $this->gridBuilder->buildGrid($rows, $package, $context, $pendingImages, $nestedTableHtml);
        $this->gridBuilder->applyRowSpans($grid);
        $this->gridBuilder->refreshCellAttributes($grid);
        $tableCells = $this->cellSegments->annotateGrid($rows, $grid);

        $rowsHtml = [];
        $headerCandidates = [];

        foreach ($grid as $row) {
            $cells = [];
            $rowIsHeader = true;

            foreach ($row as $cell) {
                if ($cell['skip']) {
                    continue;
                }

                $cells[] = $cell;
                $rowIsHeader = $rowIsHeader && $cell['bold'];
            }

            $headerCandidates[] = $rowIsHeader && $cells !== [];
            $rowsHtml[] = $cells;
        }

        $rowCount = count($rowsHtml);
        $useThead = $rowCount > 1 && ($headerCandidates[0] ?? false);
        $bodyRows = $useThead ? array_slice($rowsHtml, 1) : $rowsHtml;
        $headRow = $useThead ? ($rowsHtml[0] ?? []) : [];
        $tableStyle = $this->gridBuilder->tableStyleAttribute($table);
        $colgroup = $this->gridBuilder->colgroupHtml($table);

        $resolvedClass = $this->gridBuilder->hasVisibleBorders($table)
            ? $tableClass
            : trim($tableClass.' '.HtmlCssClasses::DOC_TABLE_LAYOUT);

        $thead = '';
        if ($useThead && $headRow !== []) {
            $thead = '<thead><tr>';
            foreach ($headRow as $cell) {
                $thead .= '<th'.$cell['attrs'].'>'.($cell['annotated_html'] ?? $cell['html'] ?: '&nbsp;').'</th>';
            }
            $thead .= '</tr></thead>';
        }

        $tbody = '<tbody>';
        foreach ($bodyRows as $cells) {
            $tbody .= '<tr>';
            foreach ($cells as $cell) {
                $tbody .= '<td'.$cell['attrs'].'>'.($cell['annotated_html'] ?? $cell['html'] ?: '&nbsp;').'</td>';
            }
            $tbody .= '</tr>';
        }
        $tbody .= '</tbody>';

        $plainParts = [];
        foreach ($rowsHtml as $cells) {
            $plainParts[] = implode(' | ', array_map(
                static fn (array $cell): string => trim(strip_tags($cell['html'])),
                $cells,
            ));
        }

        return [
            'html' => '<table class="'.$resolvedClass.'"'.$tableStyle.'>'.$colgroup.$thead.$tbody.'</table>',
            'plain' => trim(implode("\n", $plainParts)),
            'row_count' => $rowCount,
            'col_count' => count($rowsHtml[0] ?? []),
            'table_cells' => $tableCells,
            'pending_images' => $pendingImages,
        ];
    }
}

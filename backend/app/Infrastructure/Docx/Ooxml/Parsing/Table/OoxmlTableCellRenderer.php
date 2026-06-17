<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing\Table;

use App\Infrastructure\Docx\Ooxml\OoxmlNamespaces;
use App\Infrastructure\Docx\Ooxml\OoxmlPackage;
use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlRunParser;
use App\Domain\Docx\ValueObject\ParseContext;
use App\Support\Constants\OoxmlTags;
use DOMElement;

final class OoxmlTableCellRenderer
{
    public function __construct(
        private readonly OoxmlRunParser $runs,
    ) {}

    /**
     * @param  list<array{marker: string, relationship_id: string, local_path: ?string, attributes: array<string, mixed>}>  $pendingImages
     */
    public function renderCell(
        DOMElement $cell,
        OoxmlPackage $package,
        ParseContext $context,
        array &$pendingImages,
        callable $nestedTableHtml,
    ): string {
        $parts = [];

        foreach ($cell->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if ($child->localName === OoxmlTags::PARAGRAPH) {
                $parsed = $this->runs->parseContainer(
                    $child,
                    $this->paragraphStyleId($child),
                    $package,
                    $pendingImages,
                    $context,
                );

                if ($parsed['html'] !== '') {
                    $parts[] = $parsed['html'];
                }

                continue;
            }

            if ($child->localName === OoxmlTags::TABLE) {
                $nested = $nestedTableHtml($child);
                if ($nested !== '') {
                    $parts[] = $nested;
                }
            }
        }

        return implode('', $parts);
    }

    /**
     * @param  array{colspan: int, rowspan: int, attrs_data: array<string, mixed>}  $cell
     */
    public function renderCellAttributes(array $cell): string
    {
        $attrs = [];
        $data = $cell['attrs_data'] ?? [];

        if (($data['css'] ?? []) !== []) {
            $attrs[] = 'style="'.implode('; ', $data['css']).'"';
        }

        if ($cell['colspan'] > 1) {
            $attrs[] = 'colspan="'.$cell['colspan'].'"';
        }

        if ($cell['rowspan'] > 1) {
            $attrs[] = 'rowspan="'.$cell['rowspan'].'"';
        }

        return $attrs !== [] ? ' '.implode(' ', $attrs) : '';
    }

    /**
     * @return array{css: list<string>}
     */
    public function cellAttributesData(DOMElement $cell): array
    {
        $css = [];
        $tcPr = OoxmlXml::child($cell, 'tcPr');
        if (! $tcPr) {
            return ['css' => $css];
        }

        $shd = OoxmlXml::child($tcPr, 'shd');
        if ($shd) {
            $fill = OoxmlXml::attr($shd, 'fill');
            if ($fill && ! in_array(strtolower($fill), ['auto', 'ffffff'], true)) {
                $css[] = 'background-color:#'.ltrim($fill, '#');
            }
        }

        $vAlign = OoxmlXml::child($tcPr, 'vAlign');
        if ($vAlign) {
            $val = match (OoxmlXml::attr($vAlign, 'val')) {
                'center' => 'middle',
                'bottom' => 'bottom',
                'top' => 'top',
                default => null,
            };
            if ($val) {
                $css[] = 'vertical-align:'.$val;
            }
        }

        $width = $this->cellWidthRule(OoxmlXml::child($tcPr, 'tcW'));
        if ($width !== null) {
            $css[] = $width;
        }

        foreach ($this->cellBorderRules(OoxmlXml::child($tcPr, 'tcBorders')) as $rule) {
            $css[] = $rule;
        }

        return ['css' => $css];
    }

    private function cellWidthRule(?DOMElement $tcW): ?string
    {
        if (! $tcW) {
            return null;
        }

        $value = OoxmlXml::attr($tcW, 'w');
        if (! is_numeric($value)) {
            return null;
        }

        return match (OoxmlXml::attr($tcW, 'type')) {
            'pct' => 'width:'.round(((int) $value) / 50, 2).'%',
            'dxa' => 'width:'.OoxmlXml::twipsToPt($value).'pt',
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private function cellBorderRules(?DOMElement $tcBorders): array
    {
        if (! $tcBorders) {
            return [];
        }

        $rules = [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $edge = OoxmlXml::child($tcBorders, $side);
            if (! $edge) {
                continue;
            }

            $val = OoxmlXml::attr($edge, 'val');
            if ($val === null) {
                continue;
            }

            if (in_array($val, ['none', 'nil'], true)) {
                $rules[] = 'border-'.$side.':none';

                continue;
            }

            $color = OoxmlXml::attr($edge, 'color');
            $hex = ($color && ! in_array(strtolower($color), ['auto', ''], true)) ? '#'.ltrim($color, '#') : '#000';
            $rules[] = 'border-'.$side.':1px solid '.$hex;
        }

        return $rules;
    }

    public function cellColspan(DOMElement $cell): int
    {
        $tcPr = OoxmlXml::child($cell, 'tcPr');
        $gridSpan = $tcPr ? OoxmlXml::child($tcPr, 'gridSpan') : null;

        return max(1, $gridSpan ? (int) (OoxmlXml::attr($gridSpan, 'val') ?? 1) : 1);
    }

    public function cellVMerge(DOMElement $cell): ?string
    {
        $tcPr = OoxmlXml::child($cell, 'tcPr');
        $vMerge = $tcPr ? OoxmlXml::child($tcPr, 'vMerge') : null;
        if (! $vMerge) {
            return null;
        }

        $val = OoxmlXml::attr($vMerge, 'val');

        return $val === 'restart' ? 'restart' : 'continue';
    }

    public function cellIsBold(DOMElement $cell): bool
    {
        foreach (OoxmlXml::children($cell, 'p') as $paragraph) {
            foreach ($paragraph->getElementsByTagNameNS(OoxmlNamespaces::W, 'r') as $run) {
                if (! $run instanceof DOMElement) {
                    continue;
                }
                $rPr = OoxmlXml::child($run, 'rPr');
                if ($rPr && OoxmlXml::onOff($rPr, 'b')) {
                    return true;
                }
            }
        }

        return false;
    }

    private function paragraphStyleId(DOMElement $paragraph): ?string
    {
        $pPr = OoxmlXml::child($paragraph, 'pPr');
        $pStyle = $pPr ? OoxmlXml::child($pPr, 'pStyle') : null;

        return $pStyle ? OoxmlXml::attr($pStyle, 'val') : null;
    }
}

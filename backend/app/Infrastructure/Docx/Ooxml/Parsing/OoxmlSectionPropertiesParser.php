<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing;

use App\Infrastructure\Docx\Ooxml\OoxmlNamespaces;
use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use DOMDocument;
use DOMElement;

/**
 * Reads w:sectPr page geometry for PDF export.
 */
final class OoxmlSectionPropertiesParser
{
    /**
     * @return array{
     *     page_width_mm: float,
     *     page_height_mm: float,
     *     margin_top_mm: float,
     *     margin_right_mm: float,
     *     margin_bottom_mm: float,
     *     margin_left_mm: float,
     *     columns: int
     * }|null
     */
    public function parseDocument(DOMDocument $document): ?array
    {
        $body = $document->getElementsByTagNameNS(OoxmlNamespaces::W, 'body')->item(0);
        if (! $body instanceof DOMElement) {
            return null;
        }

        $sectPr = OoxmlXml::child($body, 'sectPr');
        if (! $sectPr) {
            foreach ($body->childNodes as $child) {
                if ($child instanceof DOMElement && $child->localName === 'sectPr') {
                    $sectPr = $child;

                    break;
                }
            }
        }

        return $sectPr instanceof DOMElement ? $this->parseSectPr($sectPr) : null;
    }

    /**
     * @return array{
     *     page_width_mm: float,
     *     page_height_mm: float,
     *     margin_top_mm: float,
     *     margin_right_mm: float,
     *     margin_bottom_mm: float,
     *     margin_left_mm: float,
     *     columns: int
     * }
     */
    public function parseSectPr(DOMElement $sectPr): array
    {
        $pgSz = OoxmlXml::child($sectPr, 'pgSz');
        $pgMar = OoxmlXml::child($sectPr, 'pgMar');
        $cols = OoxmlXml::child($sectPr, 'cols');

        return [
            'page_width_mm' => $this->twipsToMm($this->attrOrDefault($pgSz, 'w', '11906')),
            'page_height_mm' => $this->twipsToMm($this->attrOrDefault($pgSz, 'h', '16838')),
            'margin_top_mm' => $this->twipsToMm($this->attrOrDefault($pgMar, 'top', '851')),
            'margin_right_mm' => $this->twipsToMm($this->attrOrDefault($pgMar, 'right', '851')),
            'margin_bottom_mm' => $this->twipsToMm($this->attrOrDefault($pgMar, 'bottom', '851')),
            'margin_left_mm' => $this->twipsToMm($this->attrOrDefault($pgMar, 'left', '851')),
            'columns' => max(1, $cols ? (int) ($this->attrOrDefault($cols, 'num', '1')) : 1),
        ];
    }

    private function attrOrDefault(?DOMElement $element, string $name, string $default): string
    {
        if (! $element instanceof DOMElement) {
            return $default;
        }

        return OoxmlXml::attr($element, $name) ?? $default;
    }

    private function twipsToMm(string $twips): float
    {
        if (! is_numeric($twips)) {
            return 0.0;
        }

        return round(((float) $twips / 1440) * 25.4, 2);
    }
}

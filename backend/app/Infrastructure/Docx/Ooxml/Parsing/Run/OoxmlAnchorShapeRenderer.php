<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing\Run;

use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlAnchorLayoutParser;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlCss;
use DOMElement;

final class OoxmlAnchorShapeRenderer
{
    /** @var list<string> */
    private const CONNECTOR_PRESETS = [
        'line',
        'straightConnector1',
        'bentConnector2',
        'bentConnector3',
        'bentConnector4',
        'bentConnector5',
        'curvedConnector2',
        'curvedConnector3',
        'curvedConnector4',
        'curvedConnector5',
    ];

    public function __construct(
        private readonly OoxmlAnchorLayoutParser $anchors,
    ) {}

    public function renderFromScope(DOMElement $scope): string
    {
        $xpath = OoxmlXml::xpath($scope->ownerDocument);
        $nodes = $xpath->query('.//*[local-name()="anchor"]', $scope);
        if (! $nodes) {
            return '';
        }

        $parts = [];
        foreach ($nodes as $anchor) {
            if (! $anchor instanceof DOMElement) {
                continue;
            }

            if ($this->hasBlip($anchor) || $this->hasTextBox($anchor)) {
                continue;
            }

            $shape = $this->renderAnchorShape($anchor);
            if ($shape !== '') {
                $parts[] = $shape;
            }
        }

        return implode('', $parts);
    }

    private function renderAnchorShape(DOMElement $anchor): string
    {
        $preset = $this->presetGeometry($anchor);
        if ($preset === null || ! in_array($preset, self::CONNECTOR_PRESETS, true)) {
            return '';
        }

        if (! $this->hasVisibleLine($anchor)) {
            return '';
        }

        $layout = $this->anchors->readAnchorLayout($anchor);
        $extent = OoxmlXml::child($anchor, 'extent');
        $widthPx = max(1, $this->emuToPx(OoxmlXml::attr($extent, 'cx')) ?? 1);
        $heightPx = max(1, $this->emuToPx(OoxmlXml::attr($extent, 'cy')) ?? 1);
        $leftPx = max(0, (int) ($layout['left_px'] ?? 0));
        $topPx = max(0, (int) ($layout['top_px'] ?? 0));
        [$x1, $y1, $x2, $y2] = $this->lineEndpoints($anchor, $widthPx, $heightPx);
        $stroke = $this->lineStroke($anchor);
        [$headMarker, $tailMarker] = $this->lineMarkers($anchor);
        $markerDefs = $this->markerDefinitions($stroke, $headMarker, $tailMarker);
        $lineAttrs = ' stroke="'.$stroke.'" stroke-width="1.5"';
        if ($headMarker !== null) {
            $lineAttrs .= ' marker-start="url(#'.e($headMarker).')"';
        }
        if ($tailMarker !== null) {
            $lineAttrs .= ' marker-end="url(#'.e($tailMarker).')"';
        }

        return '<svg class="doc-anchor-shape doc-anchor-shape--'.e($preset).'"'
            .OoxmlCss::styleAttribute([
                'position:absolute',
                'z-index:1',
                'left:'.$leftPx.'px',
                'top:'.$topPx.'px',
                'width:'.$widthPx.'px',
                'height:'.$heightPx.'px',
                'overflow:visible',
                'pointer-events:none',
            ])
            .' viewBox="0 0 '.$widthPx.' '.$heightPx.'" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
            .$markerDefs
            .'<line x1="'.$x1.'" y1="'.$y1.'" x2="'.$x2.'" y2="'.$y2.'"'.$lineAttrs.'/>'
            .'</svg>';
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function lineMarkers(DOMElement $anchor): array
    {
        $xpath = OoxmlXml::xpath($anchor->ownerDocument);
        $nodes = $xpath->query('.//*[local-name()="ln"]', $anchor);
        if (! $nodes || $nodes->length === 0) {
            return [null, null];
        }

        $node = $nodes->item(0);
        if (! $node instanceof DOMElement) {
            return [null, null];
        }

        $head = OoxmlXml::child($node, 'headEnd');
        $tail = OoxmlXml::child($node, 'tailEnd');

        return [
            $this->markerIdFromEnd($head),
            $this->markerIdFromEnd($tail),
        ];
    }

    private function markerIdFromEnd(?DOMElement $end): ?string
    {
        if (! $end instanceof DOMElement) {
            return null;
        }

        $type = OoxmlXml::attr($end, 'type');
        if ($type === '' || $type === 'none') {
            return null;
        }

        return match ($type) {
            'triangle', 'arrow', 'stealth', 'diamond', 'oval' => 'doc-arrow-'.$type,
            default => 'doc-arrow-triangle',
        };
    }

    private function markerDefinitions(string $stroke, ?string $headMarker, ?string $tailMarker): string
    {
        $needed = array_values(array_unique(array_filter([$headMarker, $tailMarker])));
        if ($needed === []) {
            return '';
        }

        $defs = '';
        foreach ($needed as $markerId) {
            $defs .= '<marker id="'.e($markerId).'" viewBox="0 0 10 10" refX="9" refY="5" '
                .'markerWidth="6" markerHeight="6" orient="auto-start-reverse" markerUnits="strokeWidth">'
                .'<path d="M 0 0 L 10 5 L 0 10 z" fill="'.e($stroke).'"/>'
                .'</marker>';
        }

        return '<defs>'.$defs.'</defs>';
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private function lineEndpoints(DOMElement $anchor, int $widthPx, int $heightPx): array
    {
        $flipH = false;
        $flipV = false;
        $xpath = OoxmlXml::xpath($anchor->ownerDocument);
        $xfrmNodes = $xpath->query('.//*[local-name()="xfrm"]', $anchor);
        if ($xfrmNodes && $xfrmNodes->length > 0) {
            $xfrm = $xfrmNodes->item(0);
            if ($xfrm instanceof DOMElement) {
                $flipH = $this->isFlipOn($xfrm, 'flipH');
                $flipV = $this->isFlipOn($xfrm, 'flipV');
            }
        }

        $x1 = $flipH ? $widthPx : 0;
        $x2 = $flipH ? 0 : $widthPx;
        $y1 = $flipV ? $heightPx : 0;
        $y2 = $flipV ? 0 : $heightPx;

        return [$x1, $y1, $x2, $y2];
    }

    private function lineStroke(DOMElement $anchor): string
    {
        $xpath = OoxmlXml::xpath($anchor->ownerDocument);
        $colorNodes = $xpath->query('.//*[local-name()="ln"]/*[local-name()="solidFill"]/*[local-name()="srgbClr"]', $anchor);
        if ($colorNodes && $colorNodes->length > 0) {
            $colorNode = $colorNodes->item(0);
            if ($colorNode instanceof DOMElement) {
                $value = OoxmlXml::attr($colorNode, 'val');
                if (preg_match('/^[0-9A-Fa-f]{6}$/', $value) === 1) {
                    return '#'.$value;
                }
            }
        }

        return '#000';
    }

    private function isFlipOn(DOMElement $element, string $attribute): bool
    {
        if (! $element->hasAttribute($attribute)) {
            return false;
        }

        $value = strtolower(trim($element->getAttribute($attribute)));

        return $value !== '' && ! in_array($value, ['0', 'false', 'off'], true);
    }

    private function hasBlip(DOMElement $anchor): bool
    {
        $xpath = OoxmlXml::xpath($anchor->ownerDocument);

        return (bool) $xpath->query('.//*[local-name()="blip"]', $anchor)?->length;
    }

    private function hasTextBox(DOMElement $anchor): bool
    {
        $xpath = OoxmlXml::xpath($anchor->ownerDocument);

        return (bool) $xpath->query('.//*[local-name()="txbxContent"]', $anchor)?->length;
    }

    private function presetGeometry(DOMElement $anchor): ?string
    {
        $xpath = OoxmlXml::xpath($anchor->ownerDocument);
        $nodes = $xpath->query('.//*[local-name()="prstGeom"]', $anchor);
        if (! $nodes || $nodes->length === 0) {
            return null;
        }

        $node = $nodes->item(0);
        if (! $node instanceof DOMElement) {
            return null;
        }

        $preset = OoxmlXml::attr($node, 'prst');

        return $preset !== '' ? $preset : null;
    }

    private function hasVisibleLine(DOMElement $anchor): bool
    {
        $xpath = OoxmlXml::xpath($anchor->ownerDocument);
        $nodes = $xpath->query('.//*[local-name()="ln"]', $anchor);
        if (! $nodes || $nodes->length === 0) {
            return false;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $width = OoxmlXml::attr($node, 'w');
            if ($width === '0') {
                continue;
            }

            return true;
        }

        return false;
    }

    private function emuToPx(?string $emu): ?int
    {
        if ($emu === null || $emu === '' || preg_match('/^-?\d+$/', $emu) !== 1) {
            return null;
        }

        return (int) round((int) $emu / 9525);
    }
}

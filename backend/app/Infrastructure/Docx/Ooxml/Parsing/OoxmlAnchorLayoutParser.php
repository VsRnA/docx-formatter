<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing;

use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use DOMElement;

/**
 * Reads wp:anchor positioning for HTML absolute layout within a paragraph canvas.
 */
final class OoxmlAnchorLayoutParser
{
    private float $pageMarginLeftMm = 15.0;

    private float $pageMarginTopMm = 15.0;

    public function configurePageMarginsMm(float $left, float $top): void
    {
        $this->pageMarginLeftMm = max(0, $left);
        $this->pageMarginTopMm = max(0, $top);
    }

    public function resetPageMargins(): void
    {
        $this->pageMarginLeftMm = 15.0;
        $this->pageMarginTopMm = 15.0;
    }

    /**
     * @return array{
     *     anchored: bool,
     *     left_px: ?int,
     *     top_px: ?int,
     *     position_h_from: ?string,
     *     position_v_from: ?string
     * }
     */
    public function readAnchorLayout(DOMElement $container): array
    {
        if ($container->localName !== 'anchor') {
            return [
                'anchored' => false,
                'page_anchored' => false,
                'left_px' => null,
                'top_px' => null,
                'position_h_from' => null,
                'position_v_from' => null,
            ];
        }

        $positionH = OoxmlXml::child($container, 'positionH');
        $positionV = OoxmlXml::child($container, 'positionV');

        $hFrom = $positionH ? OoxmlXml::attr($positionH, 'relativeFrom') : null;
        $vFrom = $positionV ? OoxmlXml::attr($positionV, 'relativeFrom') : null;

        $leftPx = $this->offsetPx($positionH);
        $topPx = $this->offsetPx($positionV);

        if ($hFrom === 'page' && $leftPx !== null) {
            $leftPx = $leftPx - $this->pageMarginLeftPx();
        }

        if ($vFrom === 'page' && $topPx !== null) {
            $topPx = $topPx - $this->pageMarginTopPx();
        }

        $pageAnchored = $hFrom === 'page' || $vFrom === 'page';

        return [
            'anchored' => $leftPx !== null || $topPx !== null,
            'page_anchored' => $pageAnchored,
            'left_px' => $leftPx,
            'top_px' => $topPx,
            'position_h_from' => $hFrom,
            'position_v_from' => $vFrom,
        ];
    }

    /**
     * @param  list<array{attributes?: array<string, mixed>}>  $pendingImages
     */
    public function minCanvasHeightPx(string $html, array $pendingImages): int
    {
        $maxBottom = 0;

        foreach ($pendingImages as $pending) {
            $maxBottom = max($maxBottom, $this->bottomPxFromAttributes($pending['attributes'] ?? []));
        }

        if (preg_match_all('/class="[^"]*doc-image--anchored[^"]*"[^>]*style="([^"]*)"/', $html, $matches)) {
            foreach ($matches[1] as $style) {
                $maxBottom = max($maxBottom, $this->bottomPxFromStyle($style));
            }
        }

        if (preg_match_all('/class="[^"]*doc-textbox--anchored[^"]*"[^>]*style="([^"]*)"/', $html, $matches)) {
            foreach ($matches[1] as $style) {
                $maxBottom = max($maxBottom, $this->bottomPxFromStyle($style));
            }
        }

        return $maxBottom > 0 ? $maxBottom + 8 : 0;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function bottomPxFromAttributes(array $attributes): int
    {
        if ($attributes['page_anchored'] ?? false) {
            return 0;
        }

        if (! ($attributes['anchored'] ?? false)) {
            return 0;
        }

        return $this->bottomPxFromStyle($this->styleFromAttributes($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function styleFromAttributes(array $attributes): string
    {
        $parts = [];
        if (isset($attributes['top_px'])) {
            $parts[] = 'top:'.(int) $attributes['top_px'].'px';
        }
        if (isset($attributes['height_px'])) {
            $parts[] = 'height:'.(int) $attributes['height_px'].'px';
        }

        return implode('; ', $parts);
    }

    private function bottomPxFromStyle(string $style): int
    {
        $top = 0;
        $height = 0;

        if (preg_match('/top\s*:\s*(-?\d+)px/i', $style, $match)) {
            $top = (int) $match[1];
        }

        if (preg_match('/height\s*:\s*(\d+)px/i', $style, $match)) {
            $height = (int) $match[1];
        }

        return max(0, $top + $height);
    }

    private function offsetPx(?DOMElement $positionNode): ?int
    {
        if (! $positionNode) {
            return null;
        }

        $offset = OoxmlXml::child($positionNode, 'posOffset');
        if (! $offset instanceof DOMElement) {
            return null;
        }

        $value = trim($offset->textContent ?? '');
        // Accept negative offsets (valid in Word): the object is still anchored,
        // we just clamp the CSS coordinate to 0 to avoid overflowing the canvas.
        if ($value === '' || preg_match('/^-?\d+$/', $value) !== 1) {
            return null;
        }

        return $this->emuToPx($value);
    }

    private function emuToPx(?string $emu): ?int
    {
        if ($emu === null || $emu === '' || preg_match('/^-?\d+$/', $emu) !== 1) {
            return null;
        }

        return max(0, (int) round((int) $emu / 9525));
    }

    private function pageMarginLeftPx(): int
    {
        return (int) round(($this->pageMarginLeftMm / 25.4) * 96);
    }

    private function pageMarginTopPx(): int
    {
        return (int) round(($this->pageMarginTopMm / 25.4) * 96);
    }
}

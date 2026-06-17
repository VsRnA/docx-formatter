<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing\Layout;

use App\Infrastructure\Docx\Ooxml\OoxmlPackage;
use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlAnchorLayoutParser;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlDrawingParser;
use App\Infrastructure\Docx\Ooxml\Parsing\Run\OoxmlAnchorShapeRenderer;
use DOMElement;

/**
 * Builds spatial geometry for figure groups directly from OOXML anchor/inline
 * metadata and the rendered HTML fragments produced during parsing.
 */
final class FigureGroupGeometryCollector
{
    public function __construct(
        private readonly OoxmlDrawingParser $drawings,
        private readonly OoxmlAnchorLayoutParser $anchors,
        private readonly OoxmlAnchorShapeRenderer $shapes,
    ) {}

    /**
     * @param  list<array{marker?: string, relationship_id?: string, attributes?: array<string, mixed>}>  $pendingImages
     */
    public function collectFromParagraph(
        DOMElement $paragraph,
        OoxmlPackage $package,
        string $innerHtml,
        array $pendingImages,
    ): ?FigureGroupGeometry {
        if (! $this->isFigureGroupHtml($innerHtml)) {
            return null;
        }

        $items = [];
        $items = array_merge($items, $this->collectImages($paragraph, $package, $pendingImages, $innerHtml));
        $items = array_merge($items, $this->collectCallouts($paragraph, $innerHtml));
        $items = array_merge($items, $this->collectCalloutsFromHtml($innerHtml));
        $items = array_merge($items, $this->collectConnectors($paragraph, $innerHtml));

        if ($items === []) {
            return null;
        }

        $items = $this->dedupeImagesByMarker($items);
        $items = $this->dedupeCallouts($items);
        $items = $this->dedupeConnectors($items);

        if (count(array_filter($items, static fn (array $item): bool => $item['kind'] === 'image')) < 2
            && ! array_filter($items, static fn (array $item): bool => $item['kind'] === 'connector')) {
            return null;
        }

        $items = $this->resolveInlineImagePositions($items);

        return FigureGroupGeometry::fromItems($items);
    }

    /**
     * @param  list<array{marker?: string, relationship_id?: string, attributes?: array<string, mixed>}>  $pendingImages
     * @return list<array<string, mixed>>
     */
    private function collectImages(
        DOMElement $paragraph,
        OoxmlPackage $package,
        array $pendingImages,
        string $innerHtml,
    ): array {
        $indexedPending = $this->indexPendingImages($pendingImages);
        $items = [];
        $seenMarkers = [];

        foreach ($this->drawings->findAllBlips($paragraph) as $blip) {
            $embedId = $this->drawings->relationshipIdFromBlipElement($blip);
            if ($embedId === null || $embedId === '') {
                continue;
            }

            $attributes = $this->drawings->readImageAttributesFromBlip($blip);
            $marker = $this->resolveMarker($embedId, $attributes, $indexedPending, $seenMarkers);
            if ($marker === null) {
                continue;
            }

            $figureHtml = $this->figureHtmlByMarker($innerHtml, $marker);
            if ($figureHtml === '') {
                continue;
            }

            $leftPx = (int) ($attributes['left_px'] ?? $indexedPending[$marker]['attributes']['left_px'] ?? 0);
            $topPx = (int) ($attributes['top_px'] ?? $indexedPending[$marker]['attributes']['top_px'] ?? 0);
            $widthPx = (int) ($attributes['width_px'] ?? $this->figureWidth($figureHtml));
            $heightPx = (int) ($attributes['height_px'] ?? $this->figureHeight($figureHtml));

            $items[] = [
                'kind' => 'image',
                'left_px' => max(0, $leftPx),
                'top_px' => max(0, $topPx),
                'width_px' => max(0, $widthPx),
                'height_px' => max(0, $heightPx),
                'html' => $figureHtml,
                'marker' => $marker,
                'caption_label' => null,
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectCallouts(DOMElement $paragraph, string $innerHtml): array
    {
        if (str_contains($innerHtml, 'doc-symbol-row')) {
            return [];
        }

        $items = [];
        $xpath = OoxmlXml::xpath($paragraph->ownerDocument);
        $anchors = $xpath->query('.//*[local-name()="anchor"]', $paragraph);
        if (! $anchors) {
            return $items;
        }

        foreach ($anchors as $anchor) {
            if (! $anchor instanceof DOMElement) {
                continue;
            }

            if ($this->anchorHasBlip($anchor)) {
                continue;
            }

            $txbx = $xpath->query('.//*[local-name()="txbxContent"]', $anchor)?->item(0);
            if (! $txbx instanceof DOMElement) {
                continue;
            }

            $layout = $this->anchors->readAnchorLayout($anchor);
            $extent = OoxmlXml::child($anchor, 'extent');
            $widthPx = max(1, $this->emuToPx(OoxmlXml::attr($extent, 'cx')) ?? 1);
            $heightPx = max(1, $this->emuToPx(OoxmlXml::attr($extent, 'cy')) ?? 1);
            $leftPx = max(0, (int) ($layout['left_px'] ?? 0));
            $topPx = max(0, (int) ($layout['top_px'] ?? 0));

            $plain = trim(OoxmlXml::text($txbx));
            if ($plain === '' || $this->isPageMarkerText($plain, $widthPx, $heightPx)) {
                continue;
            }

            $overlayHtml = $this->calloutHtmlByLabel($innerHtml, $plain);
            if ($overlayHtml === '') {
                $overlayHtml = '<strong>'.e($plain).'</strong>';
            }

            $items[] = [
                'kind' => 'callout',
                'left_px' => $leftPx,
                'top_px' => $topPx,
                'width_px' => $widthPx,
                'height_px' => $heightPx,
                'html' => $overlayHtml,
                'marker' => null,
                'caption_label' => $plain,
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectCalloutsFromHtml(string $innerHtml): array
    {
        if (! str_contains($innerHtml, 'doc-textbox')) {
            return [];
        }

        $items = [];
        if (! preg_match_all(
            '/<div class="doc-textbox"([^>]*)>(.*?)<\/div>/s',
            $innerHtml,
            $matches,
            PREG_SET_ORDER,
        )) {
            return [];
        }

        foreach ($matches as $match) {
            $attrs = $match[1];
            $inner = trim($match[2]);
            $plain = trim(strip_tags($inner));
            if ($plain === '') {
                continue;
            }

            $leftPx = $this->intAttribute($attrs, 'data-anchor-left');
            if ($leftPx === null) {
                continue;
            }

            $topPx = $this->intAttribute($attrs, 'data-anchor-top') ?? 0;

            $items[] = [
                'kind' => 'callout',
                'left_px' => $leftPx,
                'top_px' => $topPx,
                'width_px' => 96,
                'height_px' => 24,
                'html' => $inner,
                'marker' => null,
                'caption_label' => $plain,
            ];
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function dedupeImagesByMarker(array $items): array
    {
        $seen = [];
        $result = [];

        foreach ($items as $item) {
            if ($item['kind'] !== 'image') {
                $result[] = $item;

                continue;
            }

            $marker = (string) ($item['marker'] ?? '');
            if ($marker === '' || ! isset($seen[$marker])) {
                if ($marker !== '') {
                    $seen[$marker] = true;
                }
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function dedupeCallouts(array $items): array
    {
        $seen = [];
        $result = [];

        foreach ($items as $item) {
            if ($item['kind'] !== 'callout') {
                $result[] = $item;

                continue;
            }

            $key = ($item['caption_label'] ?? '').'@'.$item['left_px'].':'.$item['top_px'];
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function dedupeConnectors(array $items): array
    {
        $seen = [];
        $result = [];

        foreach ($items as $item) {
            if ($item['kind'] !== 'connector') {
                $result[] = $item;

                continue;
            }

            $key = $item['left_px'].':'.$item['top_px'].':'.$item['width_px'].':'.$item['height_px'];
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $item;
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectConnectors(DOMElement $paragraph, string $innerHtml): array
    {
        $shapeHtml = $this->shapes->renderFromScope($paragraph);
        if ($shapeHtml === '' && str_contains($innerHtml, 'doc-anchor-shape')) {
            $shapeHtml = $this->extractShapeHtml($innerHtml);
        } elseif ($shapeHtml !== '' && str_contains($innerHtml, 'doc-anchor-shape')) {
            $shapeHtml = $this->extractShapeHtml($innerHtml) ?: $shapeHtml;
        }

        if ($shapeHtml === '') {
            return [];
        }

        $items = [];
        if (preg_match_all('/<svg\b[^>]*class="doc-anchor-shape[^"]*"[\s\S]*?<\/svg>/', $shapeHtml, $matches)) {
            foreach ($matches[0] as $svgHtml) {
                if (! preg_match('/style="([^"]*)"/', $svgHtml, $styleMatch)) {
                    continue;
                }

                $style = $styleMatch[1];
                $leftPx = $this->intFromStyle($style, 'left');
                $topPx = $this->intFromStyle($style, 'top');
                $widthPx = max(1, $this->intFromStyle($style, 'width'));
                $heightPx = max(1, $this->intFromStyle($style, 'height'));

                $items[] = [
                    'kind' => 'connector',
                    'left_px' => $leftPx,
                    'top_px' => $topPx,
                    'width_px' => $widthPx,
                    'height_px' => $heightPx,
                    'html' => $svgHtml,
                    'marker' => null,
                    'caption_label' => null,
                ];
            }
        }

        return $items;
    }

    /**
     * When inline images share a row, reconstruct horizontal gaps from callout anchor positions.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function resolveInlineImagePositions(array $items): array
    {
        $images = array_values(array_filter($items, static fn (array $item): bool => $item['kind'] === 'image'));
        $callouts = array_values(array_filter($items, static fn (array $item): bool => $item['kind'] === 'callout'));

        if (count($images) < 2 || count($callouts) < 2) {
            return $this->alignImagesToBottom($items, $images);
        }

        usort($callouts, static fn (array $left, array $right): int => $left['left_px'] <=> $right['left_px']);
        usort($images, static fn (array $left, array $right): int => ($left['left_px'] ?? 0) <=> ($right['left_px'] ?? 0));

        $starts = [0];
        for ($index = 1; $index < count($images); $index++) {
            $previousImage = $images[$index - 1];
            $previousCallout = $callouts[$index - 1] ?? null;
            $currentCallout = $callouts[$index] ?? null;

            if ($previousCallout !== null && $currentCallout !== null) {
                $gap = max(
                    0,
                    $currentCallout['left_px'] - $previousCallout['left_px'] - max(0, $previousImage['width_px']),
                );
                $starts[] = $starts[$index - 1] + max(0, $previousImage['width_px']) + $gap;

                continue;
            }

            $starts[] = $starts[$index - 1] + max(0, $previousImage['width_px']);
        }

        $imageIndex = 0;
        foreach ($items as $offset => $item) {
            if ($item['kind'] !== 'image') {
                continue;
            }

            $item['left_px'] = $starts[$imageIndex] ?? $item['left_px'];
            $items[$offset] = $item;
            $imageIndex++;
        }

        return $this->alignImagesToBottom($items, $images);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<array<string, mixed>>  $images
     * @return list<array<string, mixed>>
     */
    private function alignImagesToBottom(array $items, array $images): array
    {
        if ($images === []) {
            return $items;
        }

        $maxHeight = max(array_map(static fn (array $image): int => max(0, $image['height_px']), $images));

        foreach ($items as $index => $item) {
            if ($item['kind'] !== 'image') {
                continue;
            }

            $height = max(0, $item['height_px']);
            $item['top_px'] = max(0, $maxHeight - $height);
            $items[$index] = $item;
        }

        return $items;
    }

    private function isFigureGroupHtml(string $html): bool
    {
        if (str_contains($html, 'doc-symbol-row') && str_contains($html, '<figure')) {
            return true;
        }

        if (str_contains($html, 'doc-anchor-shape') && str_contains($html, '<figure')) {
            return true;
        }

        return substr_count($html, '<figure') >= 2;
    }

    /**
     * @param  list<array{marker?: string, relationship_id?: string, attributes?: array<string, mixed>}>  $pendingImages
     * @return array<string, array{marker?: string, relationship_id?: string, attributes?: array<string, mixed>}>
     */
    private function indexPendingImages(array $pendingImages): array
    {
        $indexed = [];
        foreach ($pendingImages as $pending) {
            $marker = (string) ($pending['marker'] ?? $pending['relationship_id'] ?? '');
            if ($marker !== '') {
                $indexed[$marker] = $pending;
            }
        }

        return $indexed;
    }

    /**
     * @param  array<string, array{marker?: string, relationship_id?: string, attributes?: array<string, mixed>}>  $indexedPending
     * @param  array<string, bool>  $seenMarkers
     */
    private function resolveMarker(
        string $embedId,
        array $attributes,
        array $indexedPending,
        array &$seenMarkers,
    ): ?string {
        if (isset($indexedPending[$embedId]) && ! isset($seenMarkers[$embedId])) {
            $seenMarkers[$embedId] = true;

            return $embedId;
        }

        $index = 0;
        while (isset($indexedPending[$embedId.'#'.$index])) {
            $candidate = $embedId.'#'.$index;
            if (! isset($seenMarkers[$candidate])) {
                $seenMarkers[$candidate] = true;

                return $candidate;
            }
            $index++;
        }

        foreach ($indexedPending as $marker => $pending) {
            if (isset($seenMarkers[$marker])) {
                continue;
            }

            if (($pending['relationship_id'] ?? '') === $embedId) {
                $seenMarkers[$marker] = true;

                return $marker;
            }
        }

        return null;
    }

    private function figureHtmlByMarker(string $html, string $marker): string
    {
        if (preg_match(
            '/<figure\b[^>]*\bdata-pending-marker="'.preg_quote($marker, '/').'"[^>]*>.*?<\/figure>/s',
            $html,
            $match,
        )) {
            return $match[0];
        }

        return '';
    }

    private function calloutHtmlByLabel(string $html, string $label): string
    {
        $escaped = preg_quote($label, '/');
        if (preg_match(
            '/<div class="doc-textbox"[^>]*>[\s\S]*?(?:<strong>)?'.$escaped.'(?:<\/strong>)?[\s\S]*?<\/div>/i',
            $html,
            $match,
        )) {
            $inner = preg_replace('/^<div class="doc-textbox"[^>]*>(.*)<\/div>\s*$/s', '$1', $match[0]) ?? '';

            return trim($inner);
        }

        if (preg_match('/<div class="doc-callout"[^>]*>(.*?)<\/div>/s', $html, $match)) {
            return trim($match[1]);
        }

        return '';
    }

    private function extractShapeHtml(string $html): string
    {
        if (preg_match_all('/<svg\b[^>]*class="doc-anchor-shape[^"]*"[\s\S]*?<\/svg>/', $html, $matches)) {
            return implode('', $matches[0]);
        }

        return '';
    }

    private function figureWidth(string $figureHtml): int
    {
        if (preg_match('/\bwidth="(\d+)"/', $figureHtml, $match)) {
            return (int) $match[1];
        }

        if (preg_match('/\bdata-ooxml-width="(\d+)"/', $figureHtml, $match)) {
            return (int) $match[1];
        }

        return 0;
    }

    private function figureHeight(string $figureHtml): int
    {
        if (preg_match('/\bheight="(\d+)"/', $figureHtml, $match)) {
            return (int) $match[1];
        }

        if (preg_match('/\bdata-ooxml-height="(\d+)"/', $figureHtml, $match)) {
            return (int) $match[1];
        }

        return 0;
    }

    private function intFromStyle(string $style, string $property): int
    {
        if (preg_match('/'.preg_quote($property, '/').'\s*:\s*(\d+)px/i', $style, $match)) {
            return (int) $match[1];
        }

        return 0;
    }

    private function intAttribute(string $attrs, string $name): ?int
    {
        if (preg_match('/\b'.preg_quote($name, '/').'="(\d+)"/', $attrs, $match)) {
            return (int) $match[1];
        }

        return null;
    }

    private function anchorHasBlip(DOMElement $anchor): bool
    {
        $xpath = OoxmlXml::xpath($anchor->ownerDocument);

        return (bool) $xpath->query('.//*[local-name()="blip"]', $anchor)?->length;
    }

    private function emuToPx(?string $emu): ?int
    {
        if ($emu === null || $emu === '' || ! ctype_digit($emu)) {
            return null;
        }

        return max(1, (int) round((int) $emu / 9525));
    }

    private function isPageMarkerText(string $text, int $widthPx, int $heightPx): bool
    {
        if (preg_match('/^\d{1,3}$/', trim($text)) !== 1) {
            return false;
        }

        return $widthPx <= 60 && $heightPx <= 40;
    }
}

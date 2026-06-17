<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing\Layout;

use App\Infrastructure\Docx\Ooxml\OoxmlPackage;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlCss;
use DOMElement;

final class ParagraphLayoutHelper
{
    private ?FigureGroupGeometryCollector $geometryCollector = null;

    private ?CoordinateFigureCanvasRenderer $coordinateCanvas = null;
    public function requiresDivWrapper(string $innerHtml): bool
    {
        return str_contains($innerHtml, 'doc-symbol-row')
            || str_contains($innerHtml, 'doc-image--page-decoration')
            || str_contains($innerHtml, 'doc-anchored-canvas')
            || str_contains($innerHtml, 'doc-figure-gallery')
            || str_contains($innerHtml, 'doc-figure-canvas');
    }

    public function resolveWrapperTag(string $tag, string $innerHtml): string
    {
        if ($tag !== 'p') {
            return $tag;
        }

        if ($this->requiresDivWrapper($innerHtml) || preg_match('/<(div|figure)\b/i', $innerHtml)) {
            return 'div';
        }

        return $tag;
    }

    public function wrapPageOverlay(string $innerHtml): string
    {
        if (! str_contains($innerHtml, 'doc-image--page-decoration')) {
            return $innerHtml;
        }

        return OoxmlCss::pageOverlayOpen().$innerHtml.'</div>';
    }

    /** @return list<string> */
    public function splitOnPageBreakMarkers(string $html): array
    {
        if (! str_contains($html, 'data-doc-page-break')) {
            return [$html];
        }

        $parts = preg_split('/<span[^>]*data-doc-page-break="1"[^>]*><\/span>/', $html) ?: [$html];

        return array_values(array_filter(
            array_map(static fn (string $part): string => trim($part), $parts),
            static fn (string $part): bool => $part !== '',
        ));
    }

    /** @return list<string> */
    public function extractSymbolRows(string $html): array
    {
        if (! str_contains($html, 'doc-symbol-row')) {
            return [];
        }

        $rows = [];
        $offset = 0;

        while (($start = strpos($html, '<div class="doc-symbol-row"', $offset)) !== false) {
            $openEnd = strpos($html, '>', $start);
            if ($openEnd === false) {
                break;
            }

            $depth = 1;
            $pos = $openEnd + 1;

            while ($depth > 0 && $pos < strlen($html)) {
                $nextOpen = strpos($html, '<div', $pos);
                $nextClose = strpos($html, '</div>', $pos);

                if ($nextClose === false) {
                    break;
                }

                if ($nextOpen !== false && $nextOpen < $nextClose) {
                    $depth++;
                    $pos = $nextOpen + 4;

                    continue;
                }

                $depth--;
                $pos = $nextClose + 6;
            }

            $rows[] = substr($html, $start, $pos - $start);
            $offset = $pos;
        }

        return $rows;
    }

    public function extractNonSymbolTail(string $html): string
    {
        $tail = $html;

        foreach ($this->extractSymbolRows($html) as $row) {
            $tail = str_replace($row, '', $tail);
        }

        return trim($tail);
    }

    /**
     * Word figure callouts pair inline photos with white-on-white textbox labels.
     * Render them on a coordinate canvas derived from OOXML geometry.
     *
     * @param  list<array<string, mixed>>  $pendingImages
     */
    public function buildFigureGalleryHtml(
        string $html,
        array $pendingImages = [],
        ?DOMElement $paragraph = null,
        ?OoxmlPackage $package = null,
    ): ?string {
        if ($paragraph !== null && $package !== null) {
            $coordinateCanvas = $this->buildCoordinateFigureCanvas($paragraph, $package, $html, $pendingImages);
            if ($coordinateCanvas !== null) {
                return $coordinateCanvas;
            }
        }

        $rows = $this->extractSymbolRows($html);
        if ($rows === []) {
            return null;
        }

        foreach ($rows as $row) {
            if (! $this->isFigureGallerySymbolRow($row)) {
                return null;
            }
        }

        $builder = new FigureGalleryBuilder;

        return $builder->build($rows, $this->extractNonSymbolTail($html), $pendingImages);
    }

    /**
     * @param  list<array<string, mixed>>  $pendingImages
     */
    public function buildCoordinateFigureCanvas(
        DOMElement $paragraph,
        OoxmlPackage $package,
        string $innerHtml,
        array $pendingImages,
    ): ?string {
        if (! $this->shouldUseCoordinateCanvas($innerHtml)) {
            return null;
        }

        $geometry = $this->geometryCollector()->collectFromParagraph(
            $paragraph,
            $package,
            $innerHtml,
            $pendingImages,
        );

        if ($geometry === null) {
            return null;
        }

        return $this->coordinateCanvas()->render($geometry);
    }

    /** @deprecated Use buildFigureGalleryHtml() */
    public function flattenFigureGalleryRows(string $html): ?string
    {
        return $this->buildFigureGalleryHtml($html);
    }

    public function isFigureGallerySymbolRow(string $rowHtml): bool
    {
        if (! str_contains($rowHtml, 'doc-symbol-row')) {
            return false;
        }

        if (substr_count($rowHtml, '<figure') !== 1) {
            return false;
        }

        if (! str_contains($rowHtml, 'doc-textbox')) {
            return false;
        }

        return $this->hasInvisibleCaptionText($rowHtml);
    }

    private function hasInvisibleCaptionText(string $html): bool
    {
        return preg_match('/color\s*:\s*(?:#fff(?:fff)?|white)\b/i', $html) === 1;
    }

    /**
     * @param  list<array<string, mixed>>  $pendingImages
     */
    public function shouldCreateStandaloneImageBlock(string $plain, array $pendingImages, string $innerHtml): bool
    {
        if ($plain !== '' || count($pendingImages) !== 1) {
            return false;
        }

        return $this->isStandaloneImageHtml($innerHtml);
    }

    public function isStandaloneImageHtml(string $html): bool
    {
        $withoutFigures = preg_replace('/<figure[^>]*>.*?<\/figure>/s', '', $html) ?? $html;

        return trim(strip_tags($withoutFigures)) === '';
    }

    /**
     * Diagram paragraphs combine inline photos, numeric callouts, and connector lines.
     * Keep them in one block so arrows are not split away from the image.
     */
    public function isAnchoredDiagram(string $html): bool
    {
        if (str_contains($html, 'doc-figure-canvas')) {
            return true;
        }

        return str_contains($html, 'doc-anchor-shape')
            && str_contains($html, 'doc-symbol-row')
            && str_contains($html, '<figure');
    }

    /**
     * @param  list<array<string, mixed>>  $pendingImages
     * @return list<string>
     */
    public function paragraphClasses(array $pendingImages, string $plain, string $innerHtml): array
    {
        if ($pendingImages === []) {
            return str_contains($innerHtml, 'doc-symbol-row') ? ['doc-paragraph--symbols'] : [];
        }

        if (str_contains($innerHtml, 'doc-figure-gallery') || str_contains($innerHtml, 'doc-figure-canvas')) {
            return ['doc-paragraph--figure-gallery'];
        }

        if ($this->isStandaloneImageHtml($innerHtml)) {
            return ['doc-paragraph--inline-images'];
        }

        if ($plain !== '' || str_contains($innerHtml, 'doc-textbox') || str_contains($innerHtml, 'doc-symbol-row')) {
            return ['doc-paragraph--symbols'];
        }

        return ['doc-paragraph--symbols'];
    }

    /**
     * @param  list<string>  ...$ruleSets
     * @return list<string>
     */
    public function mergeCssRules(array ...$ruleSets): array
    {
        $merged = [];
        foreach ($ruleSets as $rules) {
            foreach ($rules as $rule) {
                $property = trim(explode(':', $rule, 2)[0]);
                $merged[$property] = $rule;
            }
        }

        return array_values($merged);
    }

    private function geometryCollector(): FigureGroupGeometryCollector
    {
        if ($this->geometryCollector === null) {
            $anchors = new \App\Infrastructure\Docx\Ooxml\Parsing\OoxmlAnchorLayoutParser;
            $tempFiles = new \App\Support\TempFileManager;
            $drawings = new \App\Infrastructure\Docx\Ooxml\Parsing\OoxmlDrawingParser($tempFiles, $anchors);
            $this->geometryCollector = new FigureGroupGeometryCollector(
                $drawings,
                $anchors,
                new \App\Infrastructure\Docx\Ooxml\Parsing\Run\OoxmlAnchorShapeRenderer($anchors),
            );
        }

        return $this->geometryCollector;
    }

    private function coordinateCanvas(): CoordinateFigureCanvasRenderer
    {
        return $this->coordinateCanvas ??= new CoordinateFigureCanvasRenderer;
    }

    private function shouldUseCoordinateCanvas(string $innerHtml): bool
    {
        $figureCount = substr_count($innerHtml, '<figure');

        if ($figureCount >= 2) {
            return true;
        }

        return $figureCount >= 1 && str_contains($innerHtml, 'doc-anchor-shape');
    }
}

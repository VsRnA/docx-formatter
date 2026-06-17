<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing\Layout;

use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlCss;

final class FigureGalleryBuilder
{
    /**
     * @param  list<string>  $symbolRows
     * @param  list<array{marker?: string, relationship_id?: string, attributes?: array<string, mixed>}>  $pendingImages
     */
    public function build(array $symbolRows, string $tailHtml = '', array $pendingImages = []): string
    {
        $symbolRows = $this->sortSymbolRowsByAnchor($symbolRows);
        $ooxmlLayout = new FigureGalleryOoxmlLayout;
        $layouts = $ooxmlLayout->indexByMarker($pendingImages);
        $parsedRows = array_map(fn (string $row): array => $this->parseRow($row, $layouts, $ooxmlLayout), $symbolRows);
        $positioned = str_contains($tailHtml, 'doc-anchor-shape');
        $starts = $this->resolveFigureStartsPreservingGaps($parsedRows);

        if ($positioned) {
            return $this->buildPositionedGallery($parsedRows, $starts, $tailHtml);
        }

        $cells = [];
        foreach ($parsedRows as $index => $row) {
            $imageLeft = $starts[$index] ?? 0;
            $marginLeft = 0;
            if ($index > 0) {
                $previousIndex = $index - 1;
                $previousRight = ($starts[$previousIndex] ?? 0) + max(0, $parsedRows[$previousIndex]['width']);
                $marginLeft = max(0, $imageLeft - $previousRight);
            }

            $cells[] = $this->buildFigureCell($row, $imageLeft, $marginLeft);
        }

        $class = count($cells) === 1
            ? 'doc-figure-gallery doc-figure-gallery--single'
            : 'doc-figure-gallery';

        return '<div class="'.$class.'">'.implode('', $cells).$tailHtml.'</div>';
    }

    /**
     * @param  list<string>  $symbolRows
     * @return list<string>
     */
    private function sortSymbolRowsByAnchor(array $symbolRows): array
    {
        $indexed = [];
        foreach ($symbolRows as $index => $row) {
            $anchor = null;
            if (preg_match('/\bdata-anchor-left="(\d+)"/', $row, $match)) {
                $anchor = (int) $match[1];
            }

            $indexed[] = [
                'row' => $row,
                'anchor' => $anchor ?? (100000 + $index),
            ];
        }

        usort($indexed, static fn (array $left, array $right): int => $left['anchor'] <=> $right['anchor']);

        return array_map(static fn (array $item): string => $item['row'], $indexed);
    }

    /**
     * @return array{
     *     figureHtml: string,
     *     width: int,
     *     height: int,
     *     overlayInner: string,
     *     anchorLeft: ?int,
     *     anchorTop: ?int,
     *     imageLeft: ?int,
     *     imageTop: ?int,
     *     marker: ?string
     * }
     */
    private function parseRow(string $rowHtml, array $layouts, FigureGalleryOoxmlLayout $ooxmlLayout): array
    {
        $figureHtml = $this->extractFigureHtml($rowHtml);
        $layout = $ooxmlLayout->resolveForFigureHtml($figureHtml, $layouts);
        $attrs = '';
        $overlayInner = '';

        if (preg_match('/<div class="doc-textbox"([^>]*)>(.*?)<\/div>\s*(?:<\/div>\s*)?$/s', $rowHtml, $match)
            || preg_match('/<div class="doc-textbox"([^>]*)>(.*?)<\/div>\s*$/s', $rowHtml, $match)) {
            $attrs = $match[1];
            $overlayInner = trim($match[2]);
        }

        return [
            'figureHtml' => $figureHtml,
            'width' => $layout['width_px'] ?? $this->figureWidth($figureHtml),
            'height' => $layout['height_px'] ?? $this->figureHeight($figureHtml),
            'overlayInner' => $overlayInner,
            'anchorLeft' => $this->intAttribute($attrs, 'data-anchor-left'),
            'anchorTop' => $this->intAttribute($attrs, 'data-anchor-top'),
            'imageLeft' => $layout['left_px'],
            'imageTop' => $layout['top_px'],
            'marker' => $ooxmlLayout->markerFromFigureHtml($figureHtml),
        ];
    }

    /**
     * Reconstruct image left edges using caption anchor deltas (preserves Word gaps).
     *
     * @param  list<array{
     *     figureHtml: string,
     *     width: int,
     *     height: int,
     *     overlayInner: string,
     *     anchorLeft: ?int,
     *     anchorTop: ?int
     * }>  $rows
     * @return list<int>
     */
    private function resolveFigureStartsPreservingGaps(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $anchoredCount = count(array_filter(
            $rows,
            static fn (array $row): bool => $row['anchorLeft'] !== null,
        ));

        if ($anchoredCount < 2) {
            return $this->resolveFigureStarts($rows, false);
        }

        $starts = [0];

        for ($index = 1; $index < count($rows); $index++) {
            $previous = $rows[$index - 1];
            $current = $rows[$index];
            $previousAnchor = $previous['anchorLeft'];
            $currentAnchor = $current['anchorLeft'];

            if ($previousAnchor !== null && $currentAnchor !== null) {
                $gap = max(0, $currentAnchor - $previousAnchor - max(0, $previous['width']));
                $starts[] = $starts[$index - 1] + max(0, $previous['width']) + $gap;

                continue;
            }

            $starts[] = $starts[$index - 1] + max(0, $previous['width']);
        }

        return $starts;
    }

    /**
     * @param  list<array{
     *     figureHtml: string,
     *     width: int,
     *     height: int,
     *     overlayInner: string,
     *     anchorLeft: ?int,
     *     anchorTop: ?int
     * }>  $rows
     * @return list<int>
     */
    private function resolveFigureStarts(array $rows, bool $positioned = false): array
    {
        if ($positioned) {
            return $this->resolveFigureStartsFromAnchors($rows);
        }

        $starts = [];
        $offset = 0;

        foreach ($rows as $row) {
            $starts[] = $offset;
            $offset += max(0, $row['width']);
        }

        return $starts;
    }

    /**
     * Derive image left edges from Word anchor positions and overlay offsets.
     *
     * @param  list<array{
     *     figureHtml: string,
     *     width: int,
     *     height: int,
     *     overlayInner: string,
     *     anchorLeft: ?int,
     *     anchorTop: ?int
     * }>  $rows
     * @return list<int>
     */
    private function resolveFigureStartsFromAnchors(array $rows): array
    {
        $starts = [];
        $offset = 0;

        foreach ($rows as $row) {
            $start = $offset;

            if ($row['anchorLeft'] !== null && $row['width'] > 0) {
                $overlayLeft = max(0, $row['anchorLeft'] - $offset);
                if ($overlayLeft < $row['width']) {
                    $start = max(0, $row['anchorLeft'] - $overlayLeft);
                }
            }

            $starts[] = $start;
            $offset = max($offset, $start + max(0, $row['width']));
        }

        return $starts;
    }

    /**
     * Flat canvas layout: images, labels, and connectors share paragraph coordinates.
     *
     * @param  list<array{
     *     figureHtml: string,
     *     width: int,
     *     height: int,
     *     overlayInner: string,
     *     anchorLeft: ?int,
     *     anchorTop: ?int
     * }>  $rows
     * @param  list<int>  $starts
     */
    private function buildPositionedGallery(array $rows, array $starts, string $tailHtml): string
    {
        $maxImageHeight = max(array_map(static fn (array $row): int => $row['height'], $rows)) ?: 1;
        $captionBand = 32;
        $imageAreaHeight = max(
            $maxImageHeight,
            $this->maxShapeBottom($tailHtml),
            $this->maxOverlayBottom($rows, $starts, $maxImageHeight),
        );

        $canvasHeight = max(
            $imageAreaHeight + $captionBand,
            $this->maxShapeBottom($tailHtml),
        );

        $maxRight = max($this->maxFigureRight($rows, $starts), $this->maxShapeRight($tailHtml));

        $images = [];
        $captions = [];
        foreach ($rows as $index => $row) {
            $left = $starts[$index] ?? 0;
            $top = $this->resolveImageTop($row, $maxImageHeight);
            $images[] = $this->buildPositionedImage($row, $left, $top);
            $captions[] = $this->buildPositionedCaptionSlot($left, max(0, $row['width']));
        }

        $overlays = [];
        foreach ($rows as $index => $row) {
            if ($row['overlayInner'] !== '') {
                $imageTop = $this->resolveImageTop($row, $maxImageHeight);
                $overlays[] = $this->buildCanvasOverlay($row, $starts[$index] ?? 0, $imageTop);
            }
        }

        return '<div class="doc-figure-gallery doc-figure-gallery--positioned"'.OoxmlCss::styleAttribute([
            'position:relative',
            'display:block',
            'min-height:'.$canvasHeight.'px',
            'width:100%',
            'max-width:'.max(1, $maxRight).'px',
        ]).'>'
            .'<div class="doc-figure-gallery__canvas"'.OoxmlCss::styleAttribute([
                'position:relative',
                'height:'.$imageAreaHeight.'px',
            ]).'>'
            .implode('', $images)
            .implode('', $overlays)
            .$tailHtml
            .'</div>'
            .'<div class="doc-figure-gallery__captions"'.OoxmlCss::styleAttribute([
                'position:relative',
                'height:'.$captionBand.'px',
            ]).'>'
            .implode('', $captions)
            .'</div>'
            .'</div>';
    }

    /**
     * @param  array{
     *     figureHtml: string,
     *     width: int,
     *     height: int,
     *     overlayInner: string,
     *     anchorLeft: ?int,
     *     anchorTop: ?int
     * }  $row
     */
    private function buildPositionedImage(array $row, int $left, int $top): string
    {
        $figureHtml = $row['figureHtml'];
        if ($figureHtml === '') {
            return '';
        }

        $positionedFigure = preg_replace(
            '/^<figure\b/',
            '<figure style="position:absolute;left:'.$left.'px;top:'.$top.'px;margin:0;z-index:0"',
            $figureHtml,
            1,
        );

        return is_string($positionedFigure) ? $positionedFigure : $figureHtml;
    }

    /**
     * @param  array{
     *     figureHtml: string,
     *     width: int,
     *     height: int,
     *     overlayInner: string,
     *     anchorLeft: ?int,
     *     anchorTop: ?int,
     *     imageLeft: ?int,
     *     imageTop: ?int,
     *     marker: ?string
     * }  $row
     */
    private function resolveImageTop(array $row, int $maxImageHeight): int
    {
        if ($row['imageTop'] !== null && $row['imageTop'] > 0) {
            return $row['imageTop'];
        }

        return max(0, $maxImageHeight - max(0, $row['height']));
    }

    private function buildPositionedCaptionSlot(int $left, int $width): string
    {
        $dataAttributes = ' data-ooxml-left="'.$left.'"';
        if ($width > 0) {
            $dataAttributes .= ' data-ooxml-width="'.$width.'"';
        }

        return '<figure class="doc-figure-caption-cell"'.$dataAttributes.OoxmlCss::styleAttribute(array_values(array_filter([
            'position:absolute',
            'left:'.$left.'px',
            'top:0',
            $width > 0 ? 'width:'.$width.'px' : null,
            'margin:0',
        ]))).'><figcaption class="doc-figure-caption"></figcaption></figure>';
    }

    /**
     * @param  list<array{anchorTop: ?int, height: int, anchorLeft: ?int}>  $rows
     * @param  list<int>  $starts
     */
    private function maxOverlayBottom(array $rows, array $starts, int $maxImageHeight): int
    {
        $max = 0;

        foreach ($rows as $index => $row) {
            if ($row['anchorTop'] === null && $row['overlayInner'] === '') {
                continue;
            }

            $imageTop = $this->resolveImageTop($row, $maxImageHeight);
            $localTop = $this->overlayLocalTop($row['anchorTop'], $row['height']);
            $top = $localTop !== null ? $imageTop + $localTop : max(0, $imageTop + max(0, $row['height']) - 20);
            $max = max($max, $top + 32);
        }

        return $max;
    }

    /**
     * @param  array{
     *     figureHtml: string,
     *     width: int,
     *     height: int,
     *     overlayInner: string,
     *     anchorLeft: ?int,
     *     anchorTop: ?int
     * }  $row
     */
    private function buildCanvasOverlay(array $row, int $imageLeft, int $imageTop): string
    {
        $rules = [
            'position:absolute',
            'z-index:4',
            'pointer-events:none',
        ];

        $localLeft = $this->overlayLocalLeft($row['anchorLeft'], $imageLeft, $row['width']);
        $rules[] = 'left:'.($imageLeft + $localLeft).'px';

        $localTop = $this->overlayLocalTop($row['anchorTop'], $row['height']);
        if ($localTop !== null) {
            $rules[] = 'top:'.($imageTop + $localTop).'px';
        } elseif ($row['height'] > 0) {
            $rules[] = 'top:'.max(0, $imageTop + $row['height'] - 24).'px';
        }

        return '<div class="doc-figure-overlay"'.OoxmlCss::styleAttribute($rules).'>'.$row['overlayInner'].'</div>';
    }

    private function maxShapeBottom(string $html): int
    {
        $max = 0;

        if (preg_match_all('/class="doc-anchor-shape"[^>]*style="([^"]*)"/', $html, $matches)) {
            foreach ($matches[1] as $style) {
                $top = 0;
                $height = 0;
                if (preg_match('/top\s*:\s*(\d+)px/i', $style, $match)) {
                    $top = (int) $match[1];
                }
                if (preg_match('/height\s*:\s*(\d+)px/i', $style, $match)) {
                    $height = (int) $match[1];
                }
                $max = max($max, $top + $height);
            }
        }

        return $max;
    }

    private function maxShapeRight(string $html): int
    {
        $max = 0;

        if (preg_match_all('/class="doc-anchor-shape"[^>]*style="([^"]*)"/', $html, $matches)) {
            foreach ($matches[1] as $style) {
                $left = 0;
                $width = 0;
                if (preg_match('/left\s*:\s*(\d+)px/i', $style, $match)) {
                    $left = (int) $match[1];
                }
                if (preg_match('/width\s*:\s*(\d+)px/i', $style, $match)) {
                    $width = (int) $match[1];
                }
                $max = max($max, $left + $width);
            }
        }

        return $max;
    }

    /**
     * @param  list<array{width: int, anchorLeft: ?int}>  $rows
     * @param  list<int>  $starts
     */
    private function maxFigureRight(array $rows, array $starts): int
    {
        $max = 0;

        foreach ($rows as $index => $row) {
            $start = $starts[$index] ?? 0;
            $max = max($max, $start + $row['width']);
            if ($row['anchorLeft'] !== null) {
                $localLeft = $this->overlayLocalLeft($row['anchorLeft'], $start, $row['width']);
                $max = max($max, $start + $localLeft + 96);
            }
        }

        return $max;
    }

    /**
     * @param  list<array{
     *     figureHtml: string,
     *     width: int,
     *     height: int,
     *     overlayInner: string,
     *     anchorLeft: ?int,
     *     anchorTop: ?int
     * }>  $rows
     * @return list<int>
     * @deprecated Use resolveFigureStarts()
     */
    private function figureStartOffsets(array $rows): array
    {
        return $this->resolveFigureStarts($rows);
    }

    private function overlayLocalLeft(?int $anchorLeft, int $imageLeft, int $figureWidth): int
    {
        if ($anchorLeft === null || $figureWidth <= 0) {
            return 4;
        }

        return max(0, min($figureWidth - 1, $anchorLeft - $imageLeft));
    }

    private function overlayLocalTop(?int $anchorTop, int $figureHeight): ?int
    {
        if ($anchorTop === null) {
            return null;
        }

        if ($figureHeight <= 0) {
            return max(0, $anchorTop);
        }

        return max(0, min($figureHeight - 1, $anchorTop));
    }

    /**
     * @param  array{
     *     figureHtml: string,
     *     width: int,
     *     height: int,
     *     overlayInner: string,
     *     anchorLeft: ?int,
     *     anchorTop: ?int
     * }  $row
     */
    private function buildFigureCell(array $row, int $imageLeft, int $marginLeft = 0): string
    {
        $figureHtml = $row['figureHtml'];
        $overlayHtml = $row['overlayInner'] !== ''
            ? $this->buildOverlay($row, $imageLeft, $row['width'], $row['height'])
            : '';

        return '<figure class="doc-figure-cell"'.OoxmlCss::styleAttribute(array_values(array_filter([
            $marginLeft > 0 ? 'margin-left:'.$marginLeft.'px' : null,
        ]))).'>'
            .'<div class="doc-figure-frame"'.OoxmlCss::styleAttribute(array_values(array_filter([
                'position:relative',
                'display:inline-block',
                $row['width'] > 0 ? 'width:'.$row['width'].'px' : null,
                $row['height'] > 0 ? 'height:'.$row['height'].'px' : null,
            ]))).'>'
            .$figureHtml
            .$overlayHtml
            .'</div>'
            .'<figcaption class="doc-figure-caption"></figcaption>'
            .'</figure>';
    }

    /**
     * @param  array{
     *     figureHtml: string,
     *     width: int,
     *     height: int,
     *     overlayInner: string,
     *     anchorLeft: ?int,
     *     anchorTop: ?int
     * }  $overlay
     */
    private function buildOverlay(array $overlay, int $imageLeft, int $figureWidth, int $figureHeight): string
    {
        if ($overlay['overlayInner'] === '') {
            return '';
        }

        $rules = [
            'position:absolute',
            'z-index:2',
            'pointer-events:none',
        ];

        $localLeft = $this->overlayLocalLeft($overlay['anchorLeft'], $imageLeft, $figureWidth);
        $rules[] = 'left:'.$localLeft.'px';

        $localTop = $this->overlayLocalTop($overlay['anchorTop'], $figureHeight);
        if ($localTop !== null) {
            $rules[] = 'top:'.$localTop.'px';
        } elseif ($figureHeight > 0) {
            $rules[] = 'bottom:0';
        } else {
            $rules[] = 'top:4px';
        }

        if ($figureWidth > 0) {
            $rules[] = 'max-width:'.max(24, $figureWidth - $localLeft - 4).'px';
        }

        return '<div class="doc-figure-overlay"'.OoxmlCss::styleAttribute($rules).'>'.$overlay['overlayInner'].'</div>';
    }

    private function extractFigureHtml(string $rowHtml): string
    {
        if (preg_match('/<figure\b[^>]*>.*?<\/figure>/s', $rowHtml, $match)) {
            return $match[0];
        }

        return '';
    }

    private function figureWidth(string $figureHtml): int
    {
        if (preg_match('/\bwidth="(\d+)"/', $figureHtml, $match)) {
            return (int) $match[1];
        }

        if (preg_match('/width:(\d+)px/i', $figureHtml, $match)) {
            return (int) $match[1];
        }

        return 0;
    }

    private function figureHeight(string $figureHtml): int
    {
        if (preg_match('/\bheight="(\d+)"/', $figureHtml, $match)) {
            return (int) $match[1];
        }

        if (preg_match('/height:(\d+)px/i', $figureHtml, $match)) {
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
}

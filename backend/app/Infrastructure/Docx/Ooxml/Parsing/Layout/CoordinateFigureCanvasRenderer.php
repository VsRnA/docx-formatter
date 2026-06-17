<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing\Layout;

use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlCss;

final class CoordinateFigureCanvasRenderer
{
    public function render(FigureGroupGeometry $geometry, string $tailHtml = ''): string
    {
        $tailHtml = $this->sanitizeTailHtml($tailHtml);
        $bboxLeft = $geometry->bboxLeft;
        $bboxTop = $geometry->bboxTop;
        $imageAreaHeight = $geometry->bboxHeight;
        $captionBand = 32;
        $canvasWidth = max(1, $geometry->bboxWidth);

        $canvasParts = [];
        $captionSlots = [];

        foreach ($geometry->items as $item) {
            $left = max(0, $item['left_px'] - $bboxLeft);
            $top = max(0, $item['top_px'] - $bboxTop);

            if ($item['kind'] === 'image') {
                $positionedFigure = preg_replace(
                    '/^<figure\b/',
                    '<figure style="position:absolute;left:'.$left.'px;top:'.$top.'px;margin:0;z-index:0"',
                    $item['html'],
                    1,
                );
                if (is_string($positionedFigure)) {
                    $positionedFigure = $this->syncOoxmlLayoutAttributes($positionedFigure, $left, $top);
                }
                $canvasParts[] = is_string($positionedFigure) ? $positionedFigure : $item['html'];
                $captionSlots[] = [
                    'left' => $left,
                    'width' => max(0, $item['width_px']),
                ];

                continue;
            }

            if ($item['kind'] === 'callout') {
                $canvasParts[] = '<div class="doc-figure-overlay"'.OoxmlCss::styleAttribute([
                    'position:absolute',
                    'left:'.$left.'px',
                    'top:'.$top.'px',
                    'z-index:4',
                    'pointer-events:none',
                ]).'>'.$item['html'].'</div>';

                continue;
            }

            if ($item['kind'] === 'connector') {
                $connector = $item['html'];
                if (! str_contains($connector, 'position:absolute')) {
                    $connector = preg_replace(
                        '/^<svg\b/',
                        '<svg style="position:absolute;left:'.$left.'px;top:'.$top.'px;z-index:3"',
                        $connector,
                        1,
                    ) ?? $connector;
                } else {
                    $connector = preg_replace(
                        '/left\s*:\s*\d+px/i',
                        'left:'.$left.'px',
                        $connector,
                        1,
                    ) ?? $connector;
                    $connector = preg_replace(
                        '/top\s*:\s*\d+px/i',
                        'top:'.$top.'px',
                        $connector,
                        1,
                    ) ?? $connector;
                }

                $canvasParts[] = $connector;
            }
        }

        if ($tailHtml !== '') {
            $canvasParts[] = $this->normalizeTailHtml($tailHtml, $bboxLeft, $bboxTop);
        }

        $captions = [];
        foreach ($captionSlots as $slot) {
            $captions[] = $this->buildCaptionSlot($slot['left'], $slot['width']);
        }

        $canvasHeight = max(
            $imageAreaHeight,
            $this->maxBottomFromTail($tailHtml),
        );

        return '<div class="doc-figure-canvas"'.OoxmlCss::styleAttribute([
            'position:relative',
            'display:block',
            'width:100%',
            'max-width:'.max(1, $canvasWidth).'px',
        ]).'>'
            .'<div class="doc-figure-canvas__layer"'.OoxmlCss::styleAttribute([
                'position:relative',
                'min-height:'.$canvasHeight.'px',
                'overflow:visible',
            ]).'>'
            .implode('', $canvasParts)
            .'</div>'
            .'<div class="doc-figure-canvas__captions"'.OoxmlCss::styleAttribute([
                'position:relative',
                'height:'.$captionBand.'px',
            ]).'>'
            .implode('', $captions)
            .'</div>'
            .'</div>';
    }

    private function buildCaptionSlot(int $left, int $width): string
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

    private function normalizeTailHtml(string $tailHtml, int $bboxLeft, int $bboxTop): string
    {
        if ($bboxLeft === 0 && $bboxTop === 0) {
            return $tailHtml;
        }

        return (string) preg_replace_callback(
            '/(<(?:svg|div)[^>]*style=")([^"]*)(")/',
            function (array $matches) use ($bboxLeft, $bboxTop): string {
                $style = $matches[2];
                $style = preg_replace_callback(
                    '/left\s*:\s*(\d+)px/i',
                    static fn (array $match): string => 'left:'.max(0, (int) $match[1] - $bboxLeft).'px',
                    $style,
                ) ?? $style;
                $style = preg_replace_callback(
                    '/top\s*:\s*(\d+)px/i',
                    static fn (array $match): string => 'top:'.max(0, (int) $match[1] - $bboxTop).'px',
                    $style,
                ) ?? $style;

                return $matches[1].$style.$matches[3];
            },
            $tailHtml,
        );
    }

    private function maxBottomFromTail(string $tailHtml): int
    {
        $max = 0;

        if (preg_match_all('/style="([^"]*)"/', $tailHtml, $matches)) {
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

    private function sanitizeTailHtml(string $tailHtml): string
    {
        if ($tailHtml === '') {
            return '';
        }

        if (! str_contains($tailHtml, 'doc-symbol-row')) {
            return $tailHtml;
        }

        $helper = new ParagraphLayoutHelper;
        foreach ($helper->extractSymbolRows($tailHtml) as $row) {
            $tailHtml = str_replace($row, '', $tailHtml);
        }

        return trim($tailHtml);
    }

    private function syncOoxmlLayoutAttributes(string $html, int $left, int $top): string
    {
        foreach ([
            'data-ooxml-left' => $left,
            'data-ooxml-top' => $top,
        ] as $attribute => $value) {
            if (preg_match('/\s'.$attribute.'="\d+"/', $html) === 1) {
                $html = (string) preg_replace(
                    '/\s'.$attribute.'="\d+"/',
                    ' '.$attribute.'="'.$value.'"',
                    $html,
                    1,
                );
            } else {
                $html = preg_replace(
                    '/^<figure\b/',
                    '<figure '.$attribute.'="'.$value.'"',
                    $html,
                    1,
                ) ?? $html;
            }
        }

        return $html;
    }
}

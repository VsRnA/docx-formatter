<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing\Layout;

use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlCss;

/**
 * Converts merged symbol-row callout markup into a flat absolutely-positioned canvas.
 */
final class AnchoredCanvasLayoutNormalizer
{
    public function normalize(string $html): string
    {
        if (! str_contains($html, 'doc-anchored-canvas')) {
            return $html;
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="docx-root">'.$html.'</div></body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($document);
        $canvases = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " doc-anchored-canvas ")]');
        if ($canvases === false || $canvases->length === 0) {
            return $html;
        }

        foreach ($canvases as $canvas) {
            if (! $canvas instanceof \DOMElement) {
                continue;
            }

            $innerHtml = '';
            foreach ($canvas->childNodes as $child) {
                $innerHtml .= $document->saveHTML($child) ?? '';
            }

            if (! str_contains($innerHtml, 'doc-symbol-row')
                && ! str_contains($innerHtml, 'data-anchor-left')
                && ! str_contains($innerHtml, 'doc-textbox--anchored')
                && ! str_contains($innerHtml, 'doc-anchor-shape')) {
                continue;
            }

            while ($canvas->firstChild !== null) {
                $canvas->removeChild($canvas->firstChild);
            }

            $flatHtml = $this->flattenCanvasInner($innerHtml);
            if ($flatHtml === '') {
                continue;
            }

            if (preg_match('/data-canvas-height="(\d+)"/', $flatHtml, $match) === 1) {
                $canvasHeight = (int) $match[1];
                $style = trim($canvas->getAttribute('style'), '; ');
                if ($style !== '') {
                    $style .= '; ';
                }
                $style .= 'min-height:'.$canvasHeight.'px';
                $canvas->setAttribute('style', $style);
                $flatHtml = (string) preg_replace('/\s*data-canvas-height="\d+"\s*/', ' ', $flatHtml, 1);
            }

            $flatDocument = new \DOMDocument('1.0', 'UTF-8');
            libxml_use_internal_errors(true);
            $flatDocument->loadHTML(
                '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="docx-flat">'.$flatHtml.'</div></body></html>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
            );
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            $flatRoot = $flatDocument->getElementById('docx-flat');
            if ($flatRoot === null) {
                continue;
            }

            foreach ($flatRoot->childNodes as $child) {
                $canvas->appendChild($document->importNode($child, true));
            }
        }

        $root = $document->getElementById('docx-root');
        if ($root === null) {
            return $html;
        }

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return $result;
    }

    private function flattenCanvasInner(string $innerHtml): string
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="docx-root">'.$innerHtml.'</div></body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('docx-root');
        if ($root === null) {
            return $innerHtml;
        }

        $figures = [];
        $callouts = [];
        $shapes = [];
        $extraNodes = [];

        foreach ($root->childNodes as $child) {
            if (! $child instanceof \DOMElement) {
                if (trim($child->textContent ?? '') !== '') {
                    $extraNodes[] = $document->saveHTML($child) ?? '';
                }

                continue;
            }

            if ($child->tagName === 'svg' && str_contains($child->getAttribute('class'), 'doc-anchor-shape')) {
                $shapes[] = $document->saveHTML($child) ?? '';

                continue;
            }

            if ($child->tagName === 'figure') {
                $figures[] = $child;

                continue;
            }

            if (str_contains($child->getAttribute('class'), 'doc-textbox')) {
                $callouts[] = $child;

                continue;
            }

            if (str_contains($child->getAttribute('class'), 'doc-symbol-row')) {
                foreach ($child->getElementsByTagName('figure') as $figure) {
                    if ($figure instanceof \DOMElement) {
                        $figures[] = $figure;
                    }
                }

                foreach ($child->getElementsByTagName('svg') as $shape) {
                    if ($shape instanceof \DOMElement && str_contains($shape->getAttribute('class'), 'doc-anchor-shape')) {
                        $shapes[] = $document->saveHTML($shape) ?? '';
                    }
                }

                $xpath = new \DOMXPath($document);
                $textboxes = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " doc-textbox ")]', $child);
                if ($textboxes !== false) {
                    foreach ($textboxes as $textbox) {
                        if ($textbox instanceof \DOMElement) {
                            $callouts[] = $textbox;
                        }
                    }
                }

                continue;
            }

            $extraNodes[] = $document->saveHTML($child) ?? '';
        }

        $imageHeight = $this->maxImageHeight($figures);
        $maxBottom = $imageHeight;
        foreach ($callouts as $callout) {
            $maxBottom = max($maxBottom, $this->calloutBottom($callout));
        }

        $canvasHeight = max(1, $maxBottom + 8);
        $parts = array_merge($extraNodes, []);

        foreach ($figures as $figure) {
            $figureHtml = $document->saveHTML($figure) ?: '';
            $height = $this->figureHeight($figure);
            $topPx = max(0, $canvasHeight - ($height > 0 ? $height : $imageHeight));
            $wrapped = preg_replace(
                '/^<figure\b/',
                '<figure style="position:absolute;left:0;top:'.$topPx.'px;margin:0;z-index:0"',
                $figureHtml,
                1,
            );
            $parts[] = is_string($wrapped) ? $wrapped : $figureHtml;
        }

        foreach ($shapes as $shapeHtml) {
            $parts[] = $shapeHtml;
        }

        foreach ($callouts as $callout) {
            $parts[] = $this->renderAnchoredCallout($callout);
        }

        return '<span data-canvas-height="'.$canvasHeight.'"></span>'.implode('', $parts);
    }

    /**
     * @param  list<\DOMElement>  $figures
     */
    private function maxImageHeight(array $figures): int
    {
        $max = 0;
        foreach ($figures as $figure) {
            $max = max($max, $this->figureHeight($figure));
        }

        return $max;
    }

    private function figureHeight(\DOMElement $figure): int
    {
        if ($figure->hasAttribute('height')) {
            return (int) $figure->getAttribute('height');
        }

        foreach ($figure->getElementsByTagName('img') as $img) {
            if ($img instanceof \DOMElement && $img->hasAttribute('height')) {
                return (int) $img->getAttribute('height');
            }
        }

        return 0;
    }

    private function calloutBottom(\DOMElement $callout): int
    {
        [, $top] = $this->calloutPosition($callout);

        return max(0, $top + 20);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function calloutPosition(\DOMElement $callout): array
    {
        $left = (int) ($callout->getAttribute('data-anchor-left') ?: 0);
        $top = (int) ($callout->getAttribute('data-anchor-top') ?: 0);

        if ($left === 0 && $top === 0) {
            $style = $callout->getAttribute('style');
            if (preg_match('/left\s*:\s*(\d+)px/i', $style, $match)) {
                $left = (int) $match[1];
            }
            if (preg_match('/top\s*:\s*(\d+)px/i', $style, $match)) {
                $top = (int) $match[1];
            }
        }

        return [max(0, $left), max(0, $top)];
    }

    private function renderAnchoredCallout(\DOMElement $callout): string
    {
        [$left, $top] = $this->calloutPosition($callout);
        $inner = '';
        foreach ($callout->childNodes as $child) {
            $inner .= $callout->ownerDocument?->saveHTML($child) ?? '';
        }

        return '<div class="doc-callout"'.OoxmlCss::styleAttribute([
            'position:absolute',
            'z-index:2',
            'left:'.$left.'px',
            'top:'.$top.'px',
            'line-height:1.1',
            'white-space:nowrap',
            'pointer-events:none',
        ]).'>'.$inner.'</div>';
    }
}

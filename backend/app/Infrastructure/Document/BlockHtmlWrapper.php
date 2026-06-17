<?php

namespace App\Infrastructure\Document;

/**
 * Block wrapper markup shared with the frontend editor (doc-block doc-flow-block).
 */
final class BlockHtmlWrapper
{
    public static function wrap(string $blockId, string $type, string $innerHtml, ?array $meta = null): string
    {
        $class = 'doc-block doc-flow-block';
        if (! empty($meta['page_break_before'])) {
            $class .= ' doc-block--page-break-before doc-flow-block--page-break-before';
        }

        return sprintf(
            '<div class="%s" data-block-id="%s" data-block-type="%s">%s</div>',
            e($class),
            e($blockId),
            e($type),
            self::sanitizeBlockInnerHtml($innerHtml),
        );
    }

    public static function sanitizeBlockInnerHtml(string $html): string
    {
        return self::repairSymbolRowTextboxLayout(
            self::stripUnsupportedFigures(
                self::repairInvalidParagraphWrappers($html),
            ),
        );
    }

    /** EMF/WMF icons from Word are not renderable in browsers — drop them silently. */
    public static function stripUnsupportedFigures(string $html): string
    {
        if (! str_contains($html, 'doc-image--unsupported') && ! str_contains($html, 'data-unsupported-format')) {
            return $html;
        }

        return (string) preg_replace(
            '/<figure\b[^>]*\bdoc-image--unsupported\b[^>]*>[\s\S]*?<\/figure>/i',
            '',
            $html,
        );
    }

    /**
     * OOXML anchor offsets on textboxes inside symbol rows are meaningless in flex
     * layout and clip the first characters (inline position:absolute beats CSS).
     */
    public static function repairSymbolRowTextboxLayout(string $html): string
    {
        if (! str_contains($html, 'doc-symbol-row') || ! str_contains($html, 'doc-textbox')) {
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
        $nodes = $xpath->query(
            '//*[contains(concat(" ", normalize-space(@class), " "), " doc-symbol-row ")]'
            .'//*[contains(concat(" ", normalize-space(@class), " "), " doc-textbox ")]',
        );

        if ($nodes === false || $nodes->length === 0) {
            return $html;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }

            $classes = array_values(array_filter(
                preg_split('/\s+/', trim($node->getAttribute('class'))) ?: [],
                static fn (string $class): bool => $class !== '' && $class !== 'doc-textbox--anchored',
            ));
            $node->setAttribute('class', implode(' ', $classes));

            $style = self::stripAnchoringStyles($node->getAttribute('style'));
            if (! preg_match('/\bflex\s*:/i', $style)) {
                $style = trim($style.'; flex: 1 1 auto; min-width: 0', '; ');
            }

            if ($style === '') {
                $node->removeAttribute('style');
            } else {
                $node->setAttribute('style', $style);
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

    private static function stripAnchoringStyles(string $style): string
    {
        if ($style === '') {
            return '';
        }

        $style = (string) preg_replace('/\s*(?:position|left|top|right|bottom|z-index)\s*:[^;]*;?/i', '', $style);

        return trim($style, "; \t\n\r\0\x0B");
    }

    public static function repairInvalidParagraphWrappers(string $html): string
    {
        return (string) preg_replace_callback(
            '/<p(\s[^>]*)?>([\s\S]*?)<\/p>/i',
            static function (array $matches): string {
                if (! preg_match('/<div\b/i', $matches[2])) {
                    return $matches[0];
                }

                return '<div'.$matches[1].'>'.$matches[2].'</div>';
            },
            $html,
        );
    }

    public static function unwrapDocumentRoot(string $html): string
    {
        $trimmed = trim($html);
        if (preg_match('/^<article\b[^>]*class="[^"]*\bdocument-root\b[^"]*"[^>]*>([\s\S]*)<\/article>$/i', $trimmed, $matches)) {
            return trim($matches[1]);
        }

        return $html;
    }
}

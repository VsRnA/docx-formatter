<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing;

final class OoxmlCss
{
    public static function fontFamily(?string $font): string
    {
        $font = trim((string) $font);
        if ($font === '') {
            return 'font-family: DejaVu Serif, serif';
        }

        return 'font-family: '.$font.', DejaVu Serif, serif';
    }

    public static function styleAttribute(array $rules): string
    {
        $rules = array_values(array_filter(
            $rules,
            static fn (?string $rule): bool => is_string($rule) && trim($rule) !== '',
        ));

        if ($rules === []) {
            return '';
        }

        // Do not HTML-escape CSS: e() turns quotes in font-family into &amp;quot; and breaks PDF renderers.
        return ' style="'.implode('; ', $rules).'"';
    }

    public static function symbolRowOpen(): string
    {
        return '<div class="doc-symbol-row">';
    }

    public static function symbolIconsOpen(bool $horizontal = false): string
    {
        $class = 'doc-symbol-icons'.($horizontal ? ' doc-symbol-icons--row' : '');

        return '<div class="'.$class.'">';
    }

    public static function pageOverlayOpen(): string
    {
        return '<div class="doc-page-overlay">';
    }

    public static function textboxOpen(?int $minWidthPx = null, ?int $minHeightPx = null): string
    {
        $rules = [
            'flex: 1 1 auto',
            'min-width: 0',
        ];

        if ($minWidthPx) {
            $rules[] = 'min-width:'.$minWidthPx.'px';
        }

        if ($minHeightPx) {
            $rules[] = 'min-height:'.$minHeightPx.'px';
        }

        return '<div class="doc-textbox"'.self::styleAttribute($rules).'>';
    }
}

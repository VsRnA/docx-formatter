<?php

namespace App\Infrastructure\Document;

/**
 * Converts Quill class-based formatting to inline CSS for portable HTML output.
 */
final class EditorHtmlNormalizer
{
    public function normalize(string $html): string
    {
        if ($html === '') {
            return $html;
        }

        $alignments = [
            'ql-align-center' => 'center',
            'ql-align-right' => 'right',
            'ql-align-justify' => 'justify',
        ];

        foreach ($alignments as $class => $value) {
            $html = preg_replace_callback(
                '/<([a-z][a-z0-9]*)\\b([^>]*?)\\bclass="([^"]*\\b'.preg_quote($class, '/').'\\b[^"]*)"([^>]*)>/i',
                function (array $matches) use ($class, $value): string {
                    $tag = $matches[1];
                    $before = $matches[2];
                    $classAttr = $matches[3];
                    $after = $matches[4];
                    $newClass = trim(preg_replace('/\\b'.preg_quote($class, '/').'\\b/', '', $classAttr) ?? '');
                    $newClass = preg_replace('/\\s+/', ' ', $newClass) ?? '';

                    $attrs = trim($before.$after);
                    if ($newClass !== '') {
                        $attrs = preg_replace('/\\bclass="[^"]*"/', 'class="'.$newClass.'"', $attrs, 1, $count);
                        if ($count === 0) {
                            $attrs .= ' class="'.$newClass.'"';
                        }
                    } else {
                        $attrs = preg_replace('/\\bclass="[^"]*"\\s*/', '', $attrs) ?? $attrs;
                    }

                    if (preg_match('/\\bstyle="([^"]*)"/i', $attrs, $styleMatch)) {
                        $style = rtrim($styleMatch[1], '; ').'; text-align: '.$value;
                        $attrs = preg_replace('/\\bstyle="[^"]*"/', 'style="'.$style.'"', $attrs, 1);
                    } else {
                        $attrs .= ' style="text-align: '.$value.'"';
                    }

                    return '<'.$tag.' '.trim($attrs).'>';
                },
                $html,
            ) ?? $html;
        }

        return $this->normalizeImageStorageUrls($html);
    }

    /**
     * Symfony HtmlSanitizer encodes "=" in img src as "&#61;", which breaks mock-storage URLs.
     */
    public function normalizeImageStorageUrls(string $html): string
    {
        if ($html === '') {
            return $html;
        }

        return (string) preg_replace_callback(
            '/<img\b([^>]*)\bsrc=(["\'])([^"\']+)\2/i',
            static function (array $matches): string {
                $src = html_entity_decode($matches[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return '<img'.$matches[1].'src='.$matches[2].$src.$matches[2];
            },
            $html,
        );
    }

    public function extractImageStorageKey(string $html): ?string
    {
        if ($html === '' || ! preg_match('/<img\b[^>]*\bsrc=(["\'])([^"\']+)\1/i', $html, $matches)) {
            return null;
        }

        $src = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return self::storageKeyFromImageSrc($src);
    }

    public static function storageKeyFromImageSrc(string $src): ?string
    {
        $src = html_entity_decode(trim($src), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($src === '') {
            return null;
        }

        if (str_starts_with($src, 'data:')) {
            return null;
        }

        if (preg_match('#[?&]key=([^&"\']+)#i', $src, $matches)) {
            return rawurldecode($matches[1]);
        }

        $path = parse_url($src, PHP_URL_PATH) ?? '';
        if (preg_match('#/(documents/[a-f0-9\-]+/(?:uploads|images)/[^?"\'\s<>]+)$#i', $path, $matches)) {
            return rawurldecode($matches[1]);
        }

        if (preg_match('#^(documents/[a-f0-9\-]+/(?:uploads|images)/[^?"\'\s<>]+)$#i', $src, $matches)) {
            return $matches[1];
        }

        return null;
    }
}

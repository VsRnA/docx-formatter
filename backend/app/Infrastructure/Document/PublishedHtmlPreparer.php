<?php

namespace App\Infrastructure\Document;

use App\Domain\Shared\Port\FileStoragePort;

/**
 * Prepares HTML for export: UTF-8 document shell, inlined images.
 */
final class PublishedHtmlPreparer
{
    public function __construct(
        private readonly FileStoragePort $storage,
        private readonly EditorHtmlNormalizer $htmlNormalizer,
    ) {}

    public function prepareFragment(string $html): string
    {
        return $this->inlineImages($this->normalizeUrls(
            BlockHtmlWrapper::sanitizeBlockInnerHtml($html),
        ));
    }

    public function prepareForPdf(string $html): string
    {
        return $this->mapFontsForPdf(
            $this->normalizePageDecorationsForPdf(
                BlockHtmlWrapper::repairInvalidParagraphWrappers(
                    $this->repairCssEntities($this->prepareFragment($html)),
                ),
            ),
        );
    }

    private function repairCssEntities(string $html): string
    {
        // Legacy parser output double-encoded CSS quotes inside style attributes.
        return str_replace('&amp;quot;', '"', $html);
    }

    private function normalizePageDecorationsForPdf(string $html): string
    {
        return (string) preg_replace_callback(
            '/(<figure class="doc-image doc-image--page-decoration[^"]*"[^>]*>\s*<img\b[^>]*\sstyle=")([^"]*)(")/i',
            static function (array $matches): string {
                $style = preg_replace('/\s*height:\s*[\d.]+px\s*;?/i', '', $matches[2]) ?? $matches[2];
                $style = preg_replace('/\s*position:\s*absolute\s*;?/i', '', $style) ?? $style;
                $style = preg_replace('/\s*(?:left|right|top|z-index|pointer-events)\s*:[^;]*;?/i', '', $style) ?? $style;
                $style = trim((string) $style, '; ');

                if ($style === '') {
                    return preg_replace('/\sstyle="[^"]*"/', '', $matches[0], 1) ?? $matches[0];
                }

                return $matches[1].$style.$matches[3];
            },
            $html,
        );
    }

    public function prepareStandalone(string $title, string $bodyHtml): string
    {
        $body = BlockHtmlWrapper::repairInvalidParagraphWrappers(
            $this->repairCssEntities(
                $this->prepareFragment(BlockHtmlWrapper::unwrapDocumentRoot($bodyHtml)),
            ),
        );
        $cssPath = public_path('css/document-export.css');
        $css = is_file($cssPath) ? file_get_contents($cssPath) : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$this->escape($title)}</title>
<style>{$css}</style>
</head>
<body>
<div class="document-page">
<div class="document-page-frame">
<article class="document-root">
{$body}
</article>
</div>
</div>
</body>
</html>
HTML;
    }

    private function normalizeUrls(string $html): string
    {
        $html = $this->htmlNormalizer->normalizeImageStorageUrls($html);
        $publicBase = rtrim((string) config('app.url'), '/');

        return preg_replace(
            '#https?://(?:backend|localhost|127\.0\.0\.1)(?::\d+)?(/api/v1/mock-storage\?[^"\'\s<>]+)#',
            $publicBase.'$1',
            $html,
        ) ?? $html;
    }

    private function inlineImages(string $html): string
    {
        $maxBytes = max(1, (int) config('app.published_inline_image_max_mb', 2)) * 1024 * 1024;
        $totalBudget = max(1, (int) config('app.published_inline_image_total_budget_mb', 32)) * 1024 * 1024;
        $inlinedBytes = 0;

        return (string) preg_replace_callback(
            '/<img\b([^>]*)\bsrc=(["\'])([^"\']+)\2([^>]*)>/i',
            function (array $matches) use ($maxBytes, $totalBudget, &$inlinedBytes): string {
                $before = $matches[1];
                $quote = $matches[2];
                $src = $matches[3];
                $after = $matches[4];

                $inlined = $this->inlineSrc($src, $maxBytes, $totalBudget, $inlinedBytes);
                if ($inlined === null) {
                    return $matches[0];
                }

                return '<img'.$before.'src='.$quote.$inlined.$quote.$after.'>';
            },
            $html,
        );
    }

    private function inlineSrc(string $src, int $maxBytes, int $totalBudget, int &$inlinedBytes): ?string
    {
        $key = EditorHtmlNormalizer::storageKeyFromImageSrc($src);
        if ($key === null || ! $this->storage->exists($key)) {
            return null;
        }

        $size = $this->storage->size($key);
        if ($size !== null && ($size > $maxBytes || $inlinedBytes + $size > $totalBudget)) {
            return null;
        }

        $binary = $this->storage->get($key);
        if ($size === null) {
            $size = strlen($binary);
            if ($size > $maxBytes || $inlinedBytes + $size > $totalBudget) {
                return null;
            }
        }

        $mime = $this->mimeFromKey($key);
        $inlinedBytes += $size;

        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }

    private function mimeFromKey(string $key): string
    {
        $extension = strtolower(pathinfo($key, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/png',
        };
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function mapFontsForPdf(string $html): string
    {
        return (string) preg_replace_callback(
            '/font-family\s*:\s*([^;]+)/i',
            function (array $matches): string {
                return 'font-family: '.$this->mapFontFamilyValue($matches[1]);
            },
            $html,
        );
    }

    private function mapFontFamilyValue(string $familyList): string
    {
        $family = strtolower(html_entity_decode(trim($familyList), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if (str_contains($family, 'arial') || str_contains($family, 'helvetica') || str_contains($family, 'calibri')) {
            return 'DejaVu Sans, Liberation Sans, Arial, sans-serif';
        }

        if (str_contains($family, 'courier') || str_contains($family, 'mono')) {
            return 'DejaVu Sans Mono, Liberation Mono, monospace';
        }

        if (str_contains($family, 'times') || str_contains($family, 'serif')) {
            return 'DejaVu Serif, Liberation Serif, Times New Roman, serif';
        }

        return 'DejaVu Serif, Liberation Serif, serif';
    }
}

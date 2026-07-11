<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing\Layout;

final class ParagraphLayoutHelper
{
    /**
     * Apply simplified flowing layout rules for images in a paragraph.
     *
     * @param  list<array<string, mixed>>  $pendingImages
     */
    public function applyFlowingImageLayout(string $html, array &$pendingImages, ?string &$plain = null): string
    {
        if ($pendingImages === []) {
            return $html;
        }

        if ($this->isComplexImageLayout($html, $pendingImages)) {
            $this->markUnplacedPendingImages($pendingImages);

            $html = trim($this->stripComplexLayoutOverlays(
                $this->stripPendingFiguresFromHtml($html, $pendingImages),
            ));

            if ($plain !== null) {
                $plain = trim(strip_tags($html));
            }

            return $html;
        }

        foreach ($pendingImages as &$pending) {
            $pending['attributes'] = $this->normalizeAttributesForFlow(
                is_array($pending['attributes'] ?? null) ? $pending['attributes'] : [],
            );
        }
        unset($pending);

        if (count($pendingImages) >= 2) {
            $html = $this->wrapFiguresInGrid($html, count($pendingImages));
        }

        return $this->simplifyFiguresInHtml($html);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function normalizeAttributesForFlow(array $attributes): array
    {
        unset(
            $attributes['anchored'],
            $attributes['page_anchored'],
            $attributes['left_px'],
            $attributes['top_px'],
            $attributes['unplaced'],
        );
        $attributes['inline'] = false;
        $attributes['flowing'] = true;

        return $attributes;
    }

    public function requiresDivWrapper(string $innerHtml): bool
    {
        return str_contains($innerHtml, 'doc-image-grid')
            || preg_match('/<(div|figure)\b/i', $innerHtml) === 1;
    }

    public function resolveWrapperTag(string $tag, string $innerHtml): string
    {
        if ($tag !== 'p') {
            return $tag;
        }

        if ($this->requiresDivWrapper($innerHtml)) {
            return 'div';
        }

        return $tag;
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
     * @param  list<array<string, mixed>>  $pendingImages
     * @return list<string>
     */
    public function paragraphClasses(array $pendingImages, string $plain, string $innerHtml): array
    {
        if ($pendingImages === []) {
            return [];
        }

        if (str_contains($innerHtml, 'doc-image-grid')) {
            return ['doc-paragraph--image-grid'];
        }

        if ($this->isStandaloneImageHtml($innerHtml)) {
            return ['doc-paragraph--inline-images'];
        }

        return [];
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

    /**
     * @param  list<array<string, mixed>>  $pendingImages
     */
    private function isComplexImageLayout(string $html, array $pendingImages): bool
    {
        if ($pendingImages === []) {
            return false;
        }

        if (str_contains($html, 'doc-textbox')
            || str_contains($html, 'doc-anchor-shape')
            || str_contains($html, 'doc-symbol-row')
            || str_contains($html, 'doc-figure-canvas')
            || str_contains($html, 'doc-figure-gallery')
            || str_contains($html, 'doc-image--page-decoration')
            || str_contains($html, 'doc-anchored-canvas')) {
            return true;
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $pendingImages
     */
    private function markUnplacedPendingImages(array &$pendingImages): void
    {
        foreach ($pendingImages as &$pending) {
            $attributes = is_array($pending['attributes'] ?? null) ? $pending['attributes'] : [];
            $attributes['unplaced'] = true;
            $pending['attributes'] = $attributes;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $pendingImages
     */
    private function stripPendingFiguresFromHtml(string $html, array $pendingImages): string
    {
        foreach ($pendingImages as $pending) {
            $marker = (string) ($pending['marker'] ?? $pending['relationship_id'] ?? '');
            if ($marker === '') {
                continue;
            }

            $pattern = '/<figure\b[^>]*\bdata-pending-marker="'.preg_quote($marker, '/').'"[^>]*>.*?<\/figure>/s';
            $html = preg_replace($pattern, '', $html) ?? $html;
        }

        return trim($html);
    }

    private function stripComplexLayoutOverlays(string $html): string
    {
        $html = preg_replace('/<svg\b[^>]*\bdoc-anchor-shape\b[^>]*>.*?<\/svg>/s', '', $html) ?? $html;

        $html = preg_replace(
            '/<div\b[^>]*\bdoc-textbox--anchored\b[^>]*>.*?<\/div>/s',
            '',
            $html,
        ) ?? $html;

        return trim($html);
    }

    private function wrapFiguresInGrid(string $html, int $cols): string
    {
        if (! str_contains($html, '<figure')) {
            return $html;
        }

        $pattern = '/((?:<figure\b[^>]*>.*?<\/figure>\s*)+)/s';

        return preg_replace_callback(
            $pattern,
            static function (array $matches): string {
                $figureCount = max(1, substr_count($matches[1], '<figure'));

                return '<div class="doc-image-grid" style="--doc-image-grid-cols:'.$figureCount.'">'
                    .$matches[1]
                    .'</div>';
            },
            $html,
        ) ?? $html;
    }

    private function simplifyFiguresInHtml(string $html): string
    {
        if (! str_contains($html, '<figure')) {
            return $html;
        }

        $html = preg_replace(
            '/\bdoc-image--(?:anchored|inline|page-decoration(?:-[a-z]+)?)\b/',
            '',
            $html,
        ) ?? $html;

        $html = preg_replace_callback(
            '/(<figure\b[^>]*\bclass=")([^"]*)(")/',
            static function (array $matches): string {
                $classes = preg_split('/\s+/', trim($matches[2])) ?: [];
                $classes = array_values(array_unique(array_filter(
                    $classes,
                    static fn (string $class): bool => $class !== '' && str_starts_with($class, 'doc-image'),
                )));
                if (! in_array('doc-image', $classes, true)) {
                    $classes[] = 'doc-image';
                }

                return $matches[1].implode(' ', $classes).$matches[3];
            },
            $html,
        ) ?? $html;

        $html = preg_replace_callback(
            '/\bstyle="([^"]*)"/',
            static function (array $matches): string {
                $style = preg_replace('/\s*(?:position|left|top|z-index)\s*:[^;"]*;?/i', '', $matches[1]) ?? $matches[1];
                $style = trim((string) $style, '; ');

                return $style === '' ? '' : ' style="'.$style.'"';
            },
            $html,
        ) ?? $html;

        return $html;
    }
}

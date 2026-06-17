<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing\Layout;

/**
 * Resolves figure gallery geometry from OOXML-derived pending image metadata.
 *
 * @phpstan-type PendingImage array{
 *     marker?: string,
 *     relationship_id?: string,
 *     attributes?: array<string, mixed>
 * }
 */
final class FigureGalleryOoxmlLayout
{
    /**
     * @param  list<PendingImage>  $pendingImages
     * @return array<string, array{left_px: ?int, top_px: ?int, width_px: ?int, height_px: ?int}>
     */
    public function indexByMarker(array $pendingImages): array
    {
        $indexed = [];

        foreach ($pendingImages as $pending) {
            $marker = (string) ($pending['marker'] ?? $pending['relationship_id'] ?? '');
            if ($marker === '') {
                continue;
            }

            $attributes = is_array($pending['attributes'] ?? null) ? $pending['attributes'] : [];
            $indexed[$marker] = [
                'left_px' => isset($attributes['left_px']) ? (int) $attributes['left_px'] : null,
                'top_px' => isset($attributes['top_px']) ? (int) $attributes['top_px'] : null,
                'width_px' => isset($attributes['width_px']) ? (int) $attributes['width_px'] : null,
                'height_px' => isset($attributes['height_px']) ? (int) $attributes['height_px'] : null,
            ];
        }

        return $indexed;
    }

    /**
     * @param  array<string, array{left_px: ?int, top_px: ?int, width_px: ?int, height_px: ?int}>  $layouts
     * @return array{left_px: ?int, top_px: ?int, width_px: ?int, height_px: ?int}
     */
    public function resolveForFigureHtml(string $figureHtml, array $layouts): array
    {
        $marker = $this->markerFromFigureHtml($figureHtml);

        if ($marker !== null && isset($layouts[$marker])) {
            return $layouts[$marker];
        }

        return [
            'left_px' => $this->intDataAttribute($figureHtml, 'data-ooxml-left'),
            'top_px' => $this->intDataAttribute($figureHtml, 'data-ooxml-top'),
            'width_px' => $this->intDataAttribute($figureHtml, 'data-ooxml-width'),
            'height_px' => $this->intDataAttribute($figureHtml, 'data-ooxml-height'),
        ];
    }

    public function markerFromFigureHtml(string $figureHtml): ?string
    {
        if (preg_match('/\bdata-pending-marker="([^"]+)"/', $figureHtml, $match)) {
            return $match[1];
        }

        return null;
    }

    private function intDataAttribute(string $html, string $name): ?int
    {
        if (preg_match('/\b'.preg_quote($name, '/').'="(\d+)"/', $html, $match)) {
            return (int) $match[1];
        }

        return null;
    }
}

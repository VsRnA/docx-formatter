<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing\Image;

use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlCss;

final class OoxmlFigureHtmlBuilder
{
    /**
     * @param  array{alt?: ?string, width_px?: ?int, height_px?: ?int, inline?: bool}  $attributes
     */
    public function buildPendingFigure(array $attributes, string $marker): string
    {
        $class = $this->figureClass($attributes);

        return '<figure class="'.$class.'" data-pending-marker="'.e($marker).'"'
            .$this->ooxmlLayoutAttributes($attributes).'>'
            .$this->buildImgTag(null, $attributes, true)
            .'</figure>';
    }

    /**
     * @param  array{alt?: ?string, width_px?: ?int, height_px?: ?int, inline?: bool}  $attributes
     */
    public function buildUploadedFigure(string $url, array $attributes): string
    {
        $class = $this->figureClass($attributes);

        return '<figure class="'.$class.'"'.$this->ooxmlLayoutAttributes($attributes).'>'
            .$this->buildImgTag($url, $attributes, false)
            .'</figure>';
    }

    /**
     * Visible placeholder for formats the browser cannot render (EMF/WMF).
     * Better than dropping the image silently: the user still sees a marker
     * and the parse warning surfaces it for review.
     *
     * @param  array{alt?: ?string, width_px?: ?int, height_px?: ?int, inline?: bool}  $attributes
     */
    public function buildUnsupportedPlaceholder(array $attributes, string $format): string
    {
        $class = $this->figureClass($attributes).' doc-image--unsupported';
        $label = strtoupper(trim($format)) !== '' ? strtoupper(trim($format)) : 'IMAGE';
        $alt = trim($attributes['alt'] ?? '');
        $caption = $alt !== ''
            ? $alt
            : 'Изображение '.$label.' (формат не поддерживается браузером)';

        return '<figure class="'.$class.'" data-unsupported-format="'.e(strtolower($format)).'">'
            .'<span class="doc-image__unsupported-icon">'.e($label).'</span>'
            .'<figcaption class="doc-image__unsupported-caption">'.e($caption).'</figcaption>'
            .'</figure>';
    }

    /**
     * @param  array{alt?: ?string, width_px?: ?int, height_px?: ?int, inline?: bool}  $attributes
     */
    private function buildImgTag(?string $url, array $attributes, bool $pending): string
    {
        $parts = ['<img'];

        if ($pending) {
            $parts[] = ' data-pending="1"';
        } elseif ($url !== null) {
            $parts[] = ' src="'.e($url).'"';
        }

        $alt = trim($attributes['alt'] ?? '');
        $parts[] = ' alt="'.e($alt).'"';

        $width = $attributes['width_px'] ?? null;
        $height = $attributes['height_px'] ?? null;
        if ($width) {
            $parts[] = ' width="'.$width.'"';
        }
        if ($height) {
            $parts[] = ' height="'.$height.'"';
        }

        $style = $this->imageStyle($attributes);
        if ($style !== '') {
            $parts[] = OoxmlCss::styleAttribute([$style]);
        }

        $parts[] = ' />';

        return implode('', $parts);
    }

    /**
     * @param  array{alt?: ?string, width_px?: ?int, height_px?: ?int, inline?: bool}  $attributes
     */
    private function figureClass(array $attributes): string
    {
        $classes = ['doc-image'];

        if ($attributes['inline'] ?? false) {
            $classes[] = 'doc-image--inline';
        }

        if ($attributes['page_anchored'] ?? false) {
            $classes[] = 'doc-image--page-decoration';
            $side = $this->pageDecorationSide($attributes);
            if ($side !== null) {
                $classes[] = 'doc-image--page-decoration-'.$side;
            }
        } elseif ($attributes['anchored'] ?? false) {
            $classes[] = 'doc-image--anchored';
        }

        return implode(' ', $classes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function pageDecorationSide(array $attributes): ?string
    {
        $contentWidthPx = 680;
        $imageWidthPx = (int) ($attributes['width_px'] ?? 0);
        $leftPx = isset($attributes['left_px']) ? (int) $attributes['left_px'] : null;

        if ($leftPx !== null && $imageWidthPx > 0 && ($leftPx + $imageWidthPx) > $contentWidthPx) {
            return 'right';
        }

        if ($leftPx !== null && $leftPx <= 4) {
            return 'left';
        }

        if ($leftPx === null) {
            return 'right';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function imageStyle(array $attributes): string
    {
        $width = $attributes['width_px'] ?? null;
        $height = $attributes['height_px'] ?? null;
        $inline = $attributes['inline'] ?? false;
        $anchored = $attributes['anchored'] ?? false;
        $pageAnchored = $attributes['page_anchored'] ?? false;
        $rules = [];

        if ($width) {
            $rules[] = 'width:'.$width.'px';
        }

        if ($height && ! $pageAnchored) {
            $rules[] = 'height:'.$height.'px';
        }

        if ($pageAnchored) {
            return implode('; ', $rules);
        }

        if ($anchored) {
            $rules[] = 'position:absolute';
            if (isset($attributes['left_px'])) {
                $rules[] = 'left:'.(int) $attributes['left_px'].'px';
            }
            if (isset($attributes['top_px'])) {
                $rules[] = 'top:'.(int) $attributes['top_px'].'px';
            }
            $rules[] = 'z-index:1';
        }

        if (! $inline && ! $anchored && ! $pageAnchored && $rules === []) {
            $rules[] = 'max-width:100%';
        }

        return implode('; ', $rules);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function ooxmlLayoutAttributes(array $attributes): string
    {
        $parts = [];

        foreach ([
            'left_px' => 'data-ooxml-left',
            'top_px' => 'data-ooxml-top',
            'width_px' => 'data-ooxml-width',
            'height_px' => 'data-ooxml-height',
        ] as $key => $attribute) {
            if (isset($attributes[$key])) {
                $parts[] = $attribute.'="'.(int) $attributes[$key].'"';
            }
        }

        return $parts !== [] ? ' '.implode(' ', $parts) : '';
    }
}

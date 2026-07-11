<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing\Image;

use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlCss;

final class OoxmlFigureHtmlBuilder
{
    /**
     * @param  array{alt?: ?string, width_px?: ?int, height_px?: ?int, inline?: bool, flowing?: bool}  $attributes
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
     * @param  array{alt?: ?string, width_px?: ?int, height_px?: ?int, inline?: bool, flowing?: bool}  $attributes
     */
    public function buildUploadedFigure(string $url, array $attributes): string
    {
        $class = $this->figureClass($attributes);

        return '<figure class="'.$class.'"'.$this->ooxmlLayoutAttributes($attributes).'>'
            .$this->buildImgTag($url, $attributes, false)
            .'</figure>';
    }

    /**
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
     * @param  array{alt?: ?string, width_px?: ?int, height_px?: ?int, inline?: bool, flowing?: bool}  $attributes
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
        if ($width && ! ($attributes['flowing'] ?? false)) {
            $parts[] = ' width="'.$width.'"';
        }
        if ($height && ! ($attributes['flowing'] ?? false)) {
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
     * @param  array{alt?: ?string, width_px?: ?int, height_px?: ?int, inline?: bool, flowing?: bool}  $attributes
     */
    private function figureClass(array $attributes): string
    {
        if ($attributes['flowing'] ?? false) {
            return 'doc-image doc-image--flowing';
        }

        return 'doc-image';
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function imageStyle(array $attributes): string
    {
        if ($attributes['flowing'] ?? false) {
            return 'max-width:100%; width:100%; height:auto; display:block';
        }

        $width = $attributes['width_px'] ?? null;
        $height = $attributes['height_px'] ?? null;
        $rules = [];

        if ($width) {
            $rules[] = 'width:'.$width.'px';
        }

        if ($height) {
            $rules[] = 'height:'.$height.'px';
        }

        if ($rules === []) {
            $rules[] = 'max-width:100%';
        }

        return implode('; ', $rules);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function ooxmlLayoutAttributes(array $attributes): string
    {
        if ($attributes['flowing'] ?? false) {
            return '';
        }

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

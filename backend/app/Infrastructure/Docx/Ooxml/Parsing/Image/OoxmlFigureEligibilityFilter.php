<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing\Image;

use App\Infrastructure\Docx\Ooxml\OoxmlPackage;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlDrawingParser;
use App\Domain\Docx\ValueObject\ParseContext;

final class OoxmlFigureEligibilityFilter
{
    public function __construct(
        private readonly OoxmlDrawingParser $drawings,
    ) {}

    public function shouldSkipEmbed(OoxmlPackage $package, string $embedId, ?ParseContext $context = null): bool
    {
        if (! $this->drawings->isUnsupportedBrowserFormat($package->resolveMediaExtension($embedId))) {
            return false;
        }

        $context?->warn(
            'image_unsupported_format',
            'Skipped unsupported image format for '.$embedId,
        );

        return true;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function isDecorativeImage(array $attributes): bool
    {
        if ($attributes['doc_hidden'] ?? false) {
            return true;
        }

        $name = trim((string) ($attributes['doc_name'] ?? ''));
        $descr = trim((string) ($attributes['doc_descr'] ?? ''));
        if ($name !== '' || $descr !== '') {
            return false;
        }

        if ($attributes['page_anchored'] ?? false) {
            return false;
        }

        if ($attributes['anchored'] ?? false) {
            return false;
        }

        $width = (int) ($attributes['width_px'] ?? 0);
        $height = (int) ($attributes['height_px'] ?? 0);

        if ($width <= 0 || $height <= 0) {
            return false;
        }

        return ($width <= 80 && $height >= 400) || ($height / max(1, $width) > 6);
    }
}

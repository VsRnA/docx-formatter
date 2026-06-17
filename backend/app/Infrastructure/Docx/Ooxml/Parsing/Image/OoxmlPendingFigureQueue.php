<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing\Image;

use App\Infrastructure\Docx\Ooxml\OoxmlPackage;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlDrawingParser;
use App\Domain\Docx\ValueObject\ParseContext;
use DOMElement;

final class OoxmlPendingFigureQueue
{
    public function __construct(
        private readonly OoxmlDrawingParser $drawings,
        private readonly OoxmlFigureHtmlBuilder $figures,
        private readonly OoxmlFigureEligibilityFilter $eligibility,
    ) {}

    /**
     * @param  list<array{marker: string, relationship_id: string, local_path: ?string, unsupported?: bool, attributes: array<string, mixed>}>  $pendingImages
     */
    public function enqueue(
        OoxmlPackage $package,
        DOMElement $scope,
        string $embedId,
        array &$pendingImages,
        ?ParseContext $context = null,
        ?array $attributes = null,
        ?string $marker = null,
    ): string {
        $attributes ??= $this->drawings->readImageAttributes($scope, $embedId);
        $pageAnchored = (bool) ($attributes['page_anchored'] ?? false);
        $isAnchored = (bool) ($attributes['anchored'] ?? false);
        $attributes['inline'] = ! $isAnchored && ! $pageAnchored;

        if ($attributes['inline'] && $context !== null) {
            $widthPx = (int) ($attributes['width_px'] ?? 0);
            if ($widthPx > 0) {
                $attributes['left_px'] = $context->inlineColumnOffsetPx;
                $attributes['top_px'] = 0;
                $context->inlineColumnOffsetPx += $widthPx;
            }
        }

        $marker ??= $embedId;
        $marker = $this->uniqueMarker($marker, $embedId, $pendingImages);

        $extension = $package->resolveMediaExtension($embedId);
        if ($this->drawings->isUnsupportedBrowserFormat($extension)) {
            $context?->warn(
                'image_unsupported_format',
                'Skipped unsupported image format '.$embedId.' ('.$extension.')',
            );

            return '';
        }

        if ($this->eligibility->isDecorativeImage($attributes)) {
            $context?->warn(
                'image_decorative_filtered',
                'Skipped decorative image '.$embedId.' ('.($attributes['doc_name'] ?? 'unnamed').')',
            );

            return '';
        }

        $localPath = $this->drawings->extractToTemp($package, $embedId);
        if ($localPath === null) {
            return '';
        }

        $extension = $package->resolveMediaExtension($embedId);

        $pendingImages[] = [
            'marker' => $marker,
            'relationship_id' => $embedId,
            'local_path' => $localPath,
            'attributes' => array_merge($attributes, [
                'format' => $extension,
            ]),
        ];

        return $this->figures->buildPendingFigure($attributes, $marker);
    }

    /**
     * @param  list<array{marker: string, relationship_id: string, local_path: ?string, unsupported?: bool, attributes: array<string, mixed>}>  $pendingImages
     */
    private function uniqueMarker(string $marker, string $embedId, array $pendingImages): string
    {
        if (! $this->markerExists($pendingImages, $marker)) {
            return $marker;
        }

        $index = 0;
        do {
            $candidate = $embedId.'#'.$index;
            $index++;
        } while ($this->markerExists($pendingImages, $candidate));

        return $candidate;
    }

    /**
     * @param  list<array{marker: string, relationship_id: string, local_path: ?string, unsupported?: bool, attributes: array<string, mixed>}>  $pendingImages
     */
    private function markerExists(array $pendingImages, string $marker): bool
    {
        foreach ($pendingImages as $pending) {
            if (($pending['marker'] ?? '') === $marker) {
                return true;
            }
        }

        return false;
    }
}

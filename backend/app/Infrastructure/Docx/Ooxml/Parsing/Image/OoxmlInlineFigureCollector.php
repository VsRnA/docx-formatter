<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing\Image;

use App\Infrastructure\Docx\Ooxml\OoxmlPackage;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlDrawingParser;
use App\Domain\Docx\ValueObject\ParseContext;
use DOMElement;

final class OoxmlInlineFigureCollector
{
    public function __construct(
        private readonly OoxmlDrawingParser $drawings,
        private readonly OoxmlFigureEligibilityFilter $eligibility,
        private readonly OoxmlPendingFigureQueue $queue,
        private readonly OoxmlVmlFigureScanner $vml,
    ) {}

    /**
     * @param  list<array{marker: string, relationship_id: string, local_path: ?string, unsupported?: bool, attributes: array<string, mixed>}>  $pendingImages
     */
    public function pendingInlineFiguresFromScope(
        OoxmlPackage $package,
        DOMElement $scope,
        array &$pendingImages,
        ?ParseContext $context = null,
    ): string {
        return $this->figuresFromScope($package, $scope, $pendingImages, $context)['flow'];
    }

    /**
     * @param  list<array{marker: string, relationship_id: string, local_path: ?string, unsupported?: bool, attributes: array<string, mixed>}>  $pendingImages
     * @return array{page: string, flow: string}
     */
    public function figuresFromScope(
        OoxmlPackage $package,
        DOMElement $scope,
        array &$pendingImages,
        ?ParseContext $context = null,
    ): array {
        $page = [];
        $flow = [];

        $blips = $this->drawings->findAllBlips($scope);
        $embedCounts = [];
        foreach ($blips as $blip) {
            $embedId = $this->drawings->relationshipIdFromBlipElement($blip);
            if ($embedId === null || $embedId === '') {
                continue;
            }

            $embedCounts[$embedId] = ($embedCounts[$embedId] ?? 0) + 1;
        }

        $embedIndexes = [];
        foreach ($blips as $blip) {
            $embedId = $this->drawings->relationshipIdFromBlipElement($blip);
            if ($embedId === null || $embedId === '') {
                continue;
            }

            $marker = $embedId;
            if (($embedCounts[$embedId] ?? 0) > 1) {
                $index = $embedIndexes[$embedId] ?? 0;
                $embedIndexes[$embedId] = $index + 1;
                $marker = $embedId.'#'.$index;
            }

            $attributes = $this->drawings->readImageAttributesFromBlip($blip);
            $figure = $this->queue->enqueue($package, $scope, $embedId, $pendingImages, $context, $attributes, $marker);
            if ($figure === '') {
                continue;
            }

            if ($attributes['page_anchored'] ?? false) {
                $page[] = $figure;

                continue;
            }

            $flow[] = $figure;
        }

        $this->vml->appendFigures($package, $scope, $pendingImages, $context, $flow);

        return [
            'page' => implode('', $page),
            'flow' => implode('', $flow),
        ];
    }

    /**
     * @param  list<array{marker: string, relationship_id: string, local_path: ?string, unsupported?: bool, attributes: array<string, mixed>}>  $pendingImages
     */
    public function figuresHtmlFromScope(
        OoxmlPackage $package,
        ParseContext $context,
        DOMElement $scope,
        array &$pendingImages,
        string $metaSource = 'ooxml_table_cell',
    ): string {
        $figures = [];

        foreach ($this->drawings->findAllEmbedIds($scope) as $embedId) {
            // Unsupported formats now resolve to a visible placeholder inside enqueue().
            $figure = $this->queue->enqueue($package, $scope, $embedId, $pendingImages, $context);
            if ($figure === '') {
                $context->warn('image_extract_failed', 'Could not extract embedded image '.$embedId.' from '.$metaSource);

                continue;
            }

            $figures[] = $figure;
        }

        return implode('', $figures);
    }

    public function shouldSkipEmbed(OoxmlPackage $package, string $embedId, ?ParseContext $context = null): bool
    {
        return $this->eligibility->shouldSkipEmbed($package, $embedId, $context);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function isDecorativeImage(array $attributes): bool
    {
        return $this->eligibility->isDecorativeImage($attributes);
    }
}

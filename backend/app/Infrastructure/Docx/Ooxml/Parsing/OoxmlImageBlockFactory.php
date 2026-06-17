<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing;

use App\Domain\Docx\Entity\ParsedBlock;
use App\Domain\Docx\ValueObject\BlockType;
use App\Infrastructure\Docx\Ooxml\OoxmlPackage;
use App\Infrastructure\Docx\Ooxml\Parsing\Image\OoxmlFigureHtmlBuilder;
use App\Infrastructure\Docx\Ooxml\Parsing\Image\OoxmlInlineFigureCollector;
use App\Domain\Docx\ValueObject\ParseContext;
use DOMElement;

final class OoxmlImageBlockFactory
{
    public function __construct(
        private readonly OoxmlDrawingParser $drawings,
        private readonly OoxmlFigureHtmlBuilder $figureHtml,
        private readonly OoxmlInlineFigureCollector $inlineFigures,
    ) {}

    /**
     * @return list<ParsedBlock>
     */
    public function createBlocksFromScope(
        OoxmlPackage $package,
        ParseContext $context,
        DOMElement $scope,
        string $metaSource,
    ): array {
        $blocks = [];

        foreach ($this->drawings->findAllEmbedIds($scope) as $embedId) {
            $extension = $this->drawings->isUnsupportedBrowserFormat($package->resolveMediaExtension($embedId))
                ? $package->resolveMediaExtension($embedId)
                : null;
            if ($extension !== null) {
                $context->warn(
                    'image_unsupported_format',
                    'Skipped unsupported image format '.$embedId.' ('.$extension.')',
                );

                continue;
            }

            $attributes = $this->drawings->readImageAttributes($scope, $embedId);
            if ($this->inlineFigures->isDecorativeImage($attributes)) {
                $context->warn(
                    'image_decorative_filtered',
                    'Skipped decorative image '.$embedId.' ('.($attributes['doc_name'] ?? 'unnamed').')',
                );

                continue;
            }

            $blocks[] = $this->createBlock($package, $context, $scope, $embedId, $metaSource);
        }

        return $blocks;
    }

    public function createBlock(
        OoxmlPackage $package,
        ParseContext $context,
        DOMElement $scope,
        string $embedId,
        string $metaSource,
    ): ParsedBlock {
        $attributes = $this->drawings->readImageAttributes($scope, $embedId);
        $extension = $package->resolveMediaExtension($embedId);
        $localPath = $this->drawings->extractToTemp($package, $embedId);

        if ($localPath === null) {
            $context->warn('image_extract_failed', 'Could not extract embedded image '.$embedId);
        }

        return new ParsedBlock(
            type: BlockType::Image,
            sort: $context->nextSort(),
            html: $this->buildPendingFigure($attributes, $embedId),
            textOriginal: null,
            meta: array_filter([
                'source' => $metaSource,
                'image' => array_merge($attributes, [
                    'format' => $extension,
                ]),
            ]),
            assets: ['relationship_id' => $embedId],
            localImagePath: $localPath,
        );
    }

    private function createUnsupportedBlock(
        ParseContext $context,
        DOMElement $scope,
        string $embedId,
        string $extension,
        string $metaSource,
    ): ParsedBlock {
        $attributes = $this->drawings->readImageAttributes($scope, $embedId);

        return new ParsedBlock(
            type: BlockType::Image,
            sort: $context->nextSort(),
            html: $this->figureHtml->buildUnsupportedPlaceholder($attributes, $extension),
            textOriginal: null,
            meta: array_filter([
                'source' => $metaSource,
                'image' => array_merge($attributes, [
                    'format' => $extension,
                    'unsupported' => true,
                ]),
            ]),
            assets: ['relationship_id' => $embedId],
            localImagePath: null,
        );
    }

    /**
     * @param  list<array{marker: string, relationship_id: string, local_path: ?string, unsupported?: bool, attributes: array<string, mixed>}>  $pendingImages
     */
    public function pendingInlineFiguresFromScope(
        OoxmlPackage $package,
        DOMElement $scope,
        array &$pendingImages,
        ?ParseContext $context = null,
    ): string {
        return $this->inlineFigures->pendingInlineFiguresFromScope($package, $scope, $pendingImages, $context);
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
        return $this->inlineFigures->figuresFromScope($package, $scope, $pendingImages, $context);
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
        return $this->inlineFigures->figuresHtmlFromScope($package, $context, $scope, $pendingImages, $metaSource);
    }

    /**
     * @param  array{alt?: ?string, width_px?: ?int, height_px?: ?int, inline?: bool}  $attributes
     */
    public function buildPendingFigure(array $attributes, string $marker): string
    {
        return $this->figureHtml->buildPendingFigure($attributes, $marker);
    }

    /**
     * @param  array{alt?: ?string, width_px?: ?int, height_px?: ?int, inline?: bool}  $attributes
     */
    public function buildUploadedFigure(string $url, array $attributes): string
    {
        return $this->figureHtml->buildUploadedFigure($url, $attributes);
    }
}

<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing\Image;

use App\Infrastructure\Docx\Ooxml\OoxmlNamespaces;
use App\Infrastructure\Docx\Ooxml\OoxmlPackage;
use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use App\Domain\Docx\ValueObject\ParseContext;
use DOMElement;

final class OoxmlVmlFigureScanner
{
    public function __construct(
        private readonly OoxmlPendingFigureQueue $queue,
    ) {}

    /**
     * @param  list<array{marker: string, relationship_id: string, local_path: ?string, unsupported?: bool, attributes: array<string, mixed>}>  $pendingImages
     */
    public function appendFigures(
        OoxmlPackage $package,
        DOMElement $scope,
        array &$pendingImages,
        ?ParseContext $context,
        array &$flow,
    ): void {
        $xpath = OoxmlXml::xpath($scope->ownerDocument);
        $nodes = $xpath->query('.//*[local-name()="imagedata"]', $scope);
        if (! $nodes) {
            return;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $embedId = $node->getAttributeNS(OoxmlNamespaces::R, 'id');
            if ($embedId === '') {
                $embedId = OoxmlXml::attr($node, 'id') ?? '';
            }

            if ($embedId === '') {
                continue;
            }

            $figure = $this->queue->enqueue($package, $scope, $embedId, $pendingImages, $context);
            if ($figure !== '') {
                $flow[] = $figure;
            }
        }
    }
}

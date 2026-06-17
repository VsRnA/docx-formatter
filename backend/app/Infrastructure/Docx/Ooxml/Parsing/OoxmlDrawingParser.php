<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing;

use App\Infrastructure\Docx\Ooxml\OoxmlNamespaces;
use App\Infrastructure\Docx\Ooxml\OoxmlPackage;
use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use App\Support\TempFileManager;
use DOMElement;

final class OoxmlDrawingParser
{
    public function __construct(
        private readonly TempFileManager $tempFiles,
        private readonly OoxmlAnchorLayoutParser $anchors,
    ) {}

    public function findEmbedId(DOMElement $scope): ?string
    {
        return $this->findAllEmbedIds($scope)[0] ?? null;
    }

    /**
     * @return list<string>
     */
    public function findAllEmbedIds(DOMElement $scope): array
    {
        $ids = [];
        $seen = [];

        foreach ($this->collectBlipRelationshipIds($scope) as $relationshipId) {
            if (isset($seen[$relationshipId])) {
                continue;
            }

            $seen[$relationshipId] = true;
            $ids[] = $relationshipId;
        }

        return $ids;
    }

    /**
     * @return list<DOMElement>
     */
    public function findAllBlips(DOMElement $scope): array
    {
        $blips = [];
        $seen = [];
        $xpath = OoxmlXml::xpath($scope->ownerDocument);

        foreach (['.//a:blip', './/pic:blipFill/a:blip'] as $query) {
            $nodes = $xpath->query($query, $scope);
            if (! $nodes) {
                continue;
            }

            foreach ($nodes as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }

                $objectId = spl_object_id($node);
                if (isset($seen[$objectId])) {
                    continue;
                }

                if ($this->relationshipIdFromBlip($node) === null) {
                    continue;
                }

                $seen[$objectId] = true;
                $blips[] = $node;
            }
        }

        return $blips;
    }

    /**
     * @return array{
     *     alt: ?string,
     *     width_px: ?int,
     *     height_px: ?int,
     *     doc_name: ?string,
     *     doc_descr: ?string,
     *     doc_hidden: bool,
     *     anchored?: bool,
     *     page_anchored?: bool,
     *     left_px?: ?int,
     *     top_px?: ?int,
     *     position_h_from?: ?string,
     *     position_v_from?: ?string
     * }
     */
    public function readImageAttributesFromBlip(DOMElement $blip): array
    {
        return $this->attributesFromBlipContext($blip);
    }

    /**
     * @return array{alt: ?string, width_px: ?int, height_px: ?int}
     */
    public function readImageAttributes(DOMElement $scope, string $relationshipId): array
    {
        $xpath = OoxmlXml::xpath($scope->ownerDocument);

        foreach (['.//a:blip', './/pic:blipFill/a:blip'] as $query) {
            $nodes = $xpath->query($query, $scope);
            if (! $nodes) {
                continue;
            }

            foreach ($nodes as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }

                if ($this->relationshipIdFromBlip($node) !== $relationshipId) {
                    continue;
                }

                return $this->attributesFromBlipContext($node);
            }
        }

        $vmlNodes = $xpath->query('.//*[local-name()="imagedata"]', $scope);
        if ($vmlNodes) {
            foreach ($vmlNodes as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }

                $id = $node->getAttributeNS(OoxmlNamespaces::R, 'id');
                if ($id === '') {
                    $id = OoxmlXml::attr($node, 'id') ?? '';
                }

                if ($id === $relationshipId) {
                    return ['alt' => null, 'width_px' => null, 'height_px' => null];
                }
            }
        }

        return ['alt' => null, 'width_px' => null, 'height_px' => null];
    }

    public function extractToTemp(OoxmlPackage $package, string $relationshipId): ?string
    {
        if ($this->isUnsupportedBrowserFormat($package->resolveMediaExtension($relationshipId) ?? '')) {
            return null;
        }

        $entry = $package->resolveMediaPath($relationshipId);
        if ($entry === null) {
            return null;
        }

        $extension = strtolower(pathinfo($entry, PATHINFO_EXTENSION) ?: 'png');
        $tempPath = $this->tempFiles->createPath($extension);

        return $package->extractEntryTo($entry, $tempPath) ? $tempPath : null;
    }

    public function isUnsupportedBrowserFormat(?string $extension): bool
    {
        return in_array(strtolower((string) $extension), ['emf', 'wmf'], true);
    }

    /**
     * @return list<string>
     */
    private function collectBlipRelationshipIds(DOMElement $scope): array
    {
        $ids = [];
        $xpath = OoxmlXml::xpath($scope->ownerDocument);

        foreach (['.//a:blip', './/pic:blipFill/a:blip'] as $query) {
            $nodes = $xpath->query($query, $scope);
            if (! $nodes) {
                continue;
            }

            foreach ($nodes as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }

                $relationshipId = $this->relationshipIdFromBlip($node);
                if ($relationshipId !== null) {
                    $ids[] = $relationshipId;
                }
            }
        }

        $vmlNodes = $xpath->query('.//*[local-name()="imagedata"]', $scope);
        if ($vmlNodes) {
            foreach ($vmlNodes as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }

                $relationshipId = $node->getAttributeNS(OoxmlNamespaces::R, 'id');
                if ($relationshipId === '') {
                    $relationshipId = OoxmlXml::attr($node, 'id') ?? '';
                }

                if ($relationshipId !== '') {
                    $ids[] = $relationshipId;
                }
            }
        }

        return $ids;
    }

    /**
     * @return array{
     *     alt: ?string,
     *     width_px: ?int,
     *     height_px: ?int,
     *     doc_name: ?string,
     *     doc_descr: ?string,
     *     doc_hidden: bool,
     *     anchored?: bool,
     *     left_px?: ?int,
     *     top_px?: ?int,
     *     position_h_from?: ?string,
     *     position_v_from?: ?string
     * }
     */
    private function attributesFromBlipContext(DOMElement $blip): array
    {
        $container = $this->findDrawingContainer($blip);
        if (! $container instanceof DOMElement) {
            return [
                'alt' => null,
                'width_px' => null,
                'height_px' => null,
                'doc_name' => null,
                'doc_descr' => null,
                'doc_hidden' => false,
            ];
        }

        $alt = null;
        $widthPx = null;
        $heightPx = null;
        $docName = null;
        $docDescr = null;
        $docHidden = false;

        foreach ($container->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if (in_array($child->localName, ['docPr', 'cNvPr'], true)) {
                $alt = OoxmlXml::attr($child, 'descr')
                    ?: OoxmlXml::attr($child, 'title')
                    ?: $alt;
                $docName = OoxmlXml::attr($child, 'name') ?? $docName;
                $docDescr = OoxmlXml::attr($child, 'descr') ?? $docDescr;
                $hidden = OoxmlXml::attr($child, 'hidden');
                if ($hidden !== null && in_array(strtolower($hidden), ['1', 'true'], true)) {
                    $docHidden = true;
                }
            }

            if ($child->localName === 'extent') {
                $widthPx = $this->emuToPx(OoxmlXml::attr($child, 'cx'));
                $heightPx = $this->emuToPx(OoxmlXml::attr($child, 'cy'));
            }
        }

        $attributes = [
            'alt' => $alt !== '' ? $alt : null,
            'width_px' => $widthPx,
            'height_px' => $heightPx,
            'doc_name' => $docName !== '' ? $docName : null,
            'doc_descr' => $docDescr !== '' ? $docDescr : null,
            'doc_hidden' => $docHidden,
        ];

        if ($container->localName === 'anchor') {
            $attributes = array_merge($attributes, $this->anchors->readAnchorLayout($container));
        }

        return $attributes;
    }

    private function findDrawingContainer(DOMElement $blip): ?DOMElement
    {
        $node = $blip->parentNode;
        $inline = null;

        while ($node instanceof DOMElement) {
            if ($node->localName === 'inline' && $inline === null) {
                $inline = $node;
            }

            if ($node->localName === 'anchor') {
                return $node;
            }

            $node = $node->parentNode;
        }

        return $inline;
    }

    private function emuToPx(?string $emu): ?int
    {
        if ($emu === null || $emu === '' || ! ctype_digit($emu)) {
            return null;
        }

        return max(1, (int) round((int) $emu / 9525));
    }

    private function relationshipIdFromBlip(DOMElement $blip): ?string
    {
        foreach (['embed', 'link'] as $attribute) {
            $relationshipId = $blip->getAttributeNS(OoxmlNamespaces::R, $attribute);
            if ($relationshipId !== '') {
                return $relationshipId;
            }
        }

        return null;
    }

    public function relationshipIdFromBlipElement(DOMElement $blip): ?string
    {
        return $this->relationshipIdFromBlip($blip);
    }
}

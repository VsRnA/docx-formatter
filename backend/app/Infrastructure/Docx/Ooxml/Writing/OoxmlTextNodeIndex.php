<?php

namespace App\Infrastructure\Docx\Ooxml\Writing;

use App\Infrastructure\Docx\Ooxml\OoxmlNamespaces;
use DOMElement;

/**
 * Stable depth-first indexing of w:t nodes inside an OOXML scope.
 */
final class OoxmlTextNodeIndex
{
    /**
     * @return list<DOMElement>
     */
    public function textNodes(DOMElement $scope): array
    {
        $nodes = [];
        foreach ($scope->getElementsByTagNameNS(OoxmlNamespaces::W, 't') as $node) {
            if ($node instanceof DOMElement) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /**
     * @param  list<DOMElement>  $allNodes
     * @return list<int>
     */
    public function indicesForElement(DOMElement $element, array $allNodes): array
    {
        $indices = [];
        $nodeSet = array_flip(array_map(static fn (DOMElement $node): int => spl_object_id($node), $allNodes));

        foreach ($element->getElementsByTagNameNS(OoxmlNamespaces::W, 't') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $objectId = spl_object_id($node);
            if (isset($nodeSet[$objectId])) {
                $indices[] = $nodeSet[$objectId];
            }
        }

        return $indices;
    }

    /**
     * @param  list<int>  $innerIndices
     * @param  list<DOMElement>  $innerNodes
     * @param  list<DOMElement>  $outerNodes
     * @return list<int>
     */
    public function remapIndices(array $innerIndices, array $innerNodes, array $outerNodes): array
    {
        $remapped = [];

        foreach ($innerIndices as $innerIndex) {
            if (! isset($innerNodes[$innerIndex])) {
                continue;
            }

            $target = $innerNodes[$innerIndex];
            foreach ($outerNodes as $outerIndex => $outerNode) {
                if ($outerNode === $target) {
                    $remapped[] = $outerIndex;

                    break;
                }
            }
        }

        return array_values(array_unique($remapped));
    }
}

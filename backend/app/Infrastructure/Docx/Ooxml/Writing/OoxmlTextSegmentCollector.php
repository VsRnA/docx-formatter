<?php

namespace App\Infrastructure\Docx\Ooxml\Writing;

use App\Infrastructure\Docx\Ooxml\OoxmlNamespaces;
use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use DOMElement;

/**
 * Collects translatable text segments with w:t indices for OOXML write-back.
 *
 * @phpstan-type TextSegment array{id: int, text: string, t_indices: list<int>, translatable: bool}
 */
final class OoxmlTextSegmentCollector
{
    public function __construct(
        private readonly OoxmlTextNodeIndex $textNodes,
    ) {}

    /**
     * @return list<TextSegment>
     */
    public function collectFromParagraph(DOMElement $paragraph): array
    {
        if ($this->hasTextboxContent($paragraph)) {
            return $this->collectFromTextboxes($paragraph);
        }

        $segments = $this->collectFromDirectRuns($paragraph);

        if ($segments !== []) {
            return $segments;
        }

        $plain = trim(OoxmlXml::text($paragraph));
        if ($plain === '') {
            return [];
        }

        $allNodes = $this->textNodes->textNodes($paragraph);
        if ($allNodes === []) {
            return [];
        }

        return [
            $this->makeSegment(0, $plain, range(0, count($allNodes) - 1), $this->isTranslatableText($plain)),
        ];
    }

    /**
     * @return list<TextSegment>
     */
    public function collectFromCell(DOMElement $cell): array
    {
        $segments = [];
        $id = 0;
        $cellNodes = $this->textNodes->textNodes($cell);

        foreach (OoxmlXml::children($cell, 'p') as $paragraph) {
            $paragraphNodes = $this->textNodes->textNodes($paragraph);

            foreach ($this->collectFromParagraph($paragraph) as $segment) {
                $indices = $this->textNodes->remapIndices(
                    $segment['t_indices'],
                    $paragraphNodes,
                    $cellNodes,
                );
                $segments[] = $this->makeSegment($id++, $segment['text'], $indices, $segment['translatable']);
            }
        }

        if ($segments === []) {
            $plain = trim(OoxmlXml::text($cell));
            if ($plain !== '') {
                $allNodes = $this->textNodes->textNodes($cell);
                if ($allNodes !== []) {
                    $segments[] = $this->makeSegment(0, $plain, range(0, count($allNodes) - 1), $this->isTranslatableText($plain));
                }
            }
        }

        return $segments;
    }

    /**
     * @return list<TextSegment>
     */
    private function collectFromTextboxes(DOMElement $scope): array
    {
        $segments = [];
        $id = 0;
        $allNodes = $this->textNodes->textNodes($scope);
        $xpath = OoxmlXml::xpath($scope->ownerDocument);

        $textBoxes = $xpath->query('.//*[local-name()="txbxContent"]', $scope);
        if (! $textBoxes) {
            return [];
        }

        foreach ($textBoxes as $textBox) {
            if (! $textBox instanceof DOMElement) {
                continue;
            }

            if (OoxmlXml::isInsideMarkupCompatibilityFallback($textBox)) {
                continue;
            }

            foreach (OoxmlXml::children($textBox, 'p') as $paragraph) {
                $text = trim(OoxmlXml::text($paragraph));
                if ($text === '' || $this->isPageMarkerText($text)) {
                    continue;
                }

                $indices = $this->textNodes->indicesForElement($paragraph, $allNodes);
                if ($indices === []) {
                    continue;
                }

                $segments[] = $this->makeSegment(
                    $id++,
                    $text,
                    $indices,
                    $this->isTranslatableText($text),
                );
            }
        }

        $outerText = trim($this->textOutsideTextboxes($scope));
        if ($outerText !== '' && $this->isTranslatableText($outerText)) {
            $indices = $this->indicesOutsideTextboxes($scope, $allNodes);
            if ($indices !== []) {
                $segments[] = $this->makeSegment($id++, $outerText, $indices, true);
            }
        }

        return $this->dedupeIdenticalSegments($segments);
    }

    /**
     * @return list<TextSegment>
     */
    private function collectFromDirectRuns(DOMElement $paragraph): array
    {
        $segments = [];
        $id = 0;
        $allNodes = $this->textNodes->textNodes($paragraph);
        $bufferText = '';
        $bufferIndices = [];

        foreach ($this->directRuns($paragraph) as $run) {
            $runText = str_replace("\u{00A0}", ' ', OoxmlXml::text($run));
            if (trim($runText) === '') {
                continue;
            }

            $indices = $this->textNodes->indicesForElement($run, $allNodes);
            if ($indices === []) {
                continue;
            }

            if (! $this->isTranslatableText($runText)) {
                if ($bufferText !== '') {
                    $segments[] = $this->makeSegment($id++, $bufferText, $bufferIndices, true);
                    $bufferText = '';
                    $bufferIndices = [];
                }

                $segments[] = $this->makeSegment($id++, $runText, $indices, false);

                continue;
            }

            $bufferText .= $runText;
            $bufferIndices = array_merge($bufferIndices, $indices);
        }

        if (trim($bufferText) !== '') {
            $segments[] = $this->makeSegment($id++, trim($bufferText), $bufferIndices, true);
        }

        return $this->dedupeIdenticalSegments($segments);
    }

    /**
     * @return list<DOMElement>
     */
    private function directRuns(DOMElement $paragraph): array
    {
        $runs = [];

        foreach ($paragraph->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if ($child->localName === 'r') {
                $runs[] = $child;

                continue;
            }

            if (in_array($child->localName, ['hyperlink', 'fldSimple', 'smartTag'], true)) {
                foreach ($child->getElementsByTagNameNS(OoxmlNamespaces::W, 'r') as $run) {
                    if ($run instanceof DOMElement) {
                        $runs[] = $run;
                    }
                }
            }
        }

        return $runs;
    }

    private function hasTextboxContent(DOMElement $scope): bool
    {
        $xpath = OoxmlXml::xpath($scope->ownerDocument);
        $nodes = $xpath->query('.//*[local-name()="txbxContent"]', $scope);

        return $nodes !== false && $nodes->length > 0;
    }

    /**
     * @param  list<DOMElement>  $allNodes
     * @return list<int>
     */
    private function indicesOutsideTextboxes(DOMElement $scope, array $allNodes): array
    {
        $indices = [];
        $nodeSet = array_flip(array_map(static fn (DOMElement $node): int => spl_object_id($node), $allNodes));

        foreach ($this->directRuns($scope) as $run) {
            if ($this->isInsideTextbox($run)) {
                continue;
            }

            foreach ($this->textNodes->indicesForElement($run, $allNodes) as $index) {
                $indices[] = $index;
            }
        }

        return array_values(array_unique($indices));
    }

    private function textOutsideTextboxes(DOMElement $scope): string
    {
        $parts = [];

        foreach ($this->directRuns($scope) as $run) {
            if ($this->isInsideTextbox($run)) {
                continue;
            }

            $text = str_replace("\u{00A0}", ' ', $this->plainTextInRunSurface($run));
            if (trim($text) !== '') {
                $parts[] = $text;
            }
        }

        return implode('', $parts);
    }

    private function isInsideTextbox(DOMElement $element): bool
    {
        $node = $element->parentNode;

        while ($node instanceof DOMElement) {
            if ($node->localName === 'txbxContent') {
                return true;
            }

            $node = $node->parentNode;
        }

        return false;
    }

    private function isTranslatableText(string $text): bool
    {
        $trimmed = trim(str_replace("\u{00A0}", ' ', $text));
        if ($trimmed === '') {
            return false;
        }

        if ($this->isPageMarkerText($trimmed)) {
            return false;
        }

        if (preg_match('/^[\d\s\.…\x{2003}]+$/u', $trimmed) === 1) {
            return false;
        }

        return true;
    }

    private function isPageMarkerText(string $text): bool
    {
        return preg_match('/^\d{1,3}$/', trim($text)) === 1;
    }

    private function plainTextInRunSurface(DOMElement $run): string
    {
        $parts = [];

        foreach ($run->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 't') {
                $parts[] = $child->textContent;
            }
        }

        return implode('', $parts);
    }

    /**
     * @param  list<TextSegment>  $segments
     * @return list<TextSegment>
     */
    private function dedupeIdenticalSegments(array $segments): array
    {
        if ($segments === []) {
            return [];
        }

        $result = [];
        $indexByText = [];

        foreach ($segments as $segment) {
            $text = (string) $segment['text'];
            if ($text === '') {
                continue;
            }

            if (isset($indexByText[$text])) {
                $existingIndex = $indexByText[$text];
                $result[$existingIndex]['t_indices'] = array_values(array_unique(array_merge(
                    $result[$existingIndex]['t_indices'],
                    $segment['t_indices'],
                )));

                continue;
            }

            $indexByText[$text] = count($result);
            $result[] = $segment;
        }

        foreach ($result as $index => $segment) {
            $result[$index]['id'] = $index;
        }

        return $result;
    }

    /**
     * @param  list<int>  $indices
     * @return TextSegment
     */
    private function makeSegment(int $id, string $text, array $indices, bool $translatable): array
    {
        return [
            'id' => $id,
            'text' => $text,
            't_indices' => array_values(array_unique($indices)),
            'translatable' => $translatable,
        ];
    }
}

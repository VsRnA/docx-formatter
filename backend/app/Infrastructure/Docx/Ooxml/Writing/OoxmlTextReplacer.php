<?php

namespace App\Infrastructure\Docx\Ooxml\Writing;

use App\Infrastructure\Docx\Ooxml\OoxmlNamespaces;
use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use DOMElement;

final class OoxmlTextReplacer
{
    public function __construct(
        private readonly OoxmlTextNodeIndex $textNodeIndex,
    ) {}

    /**
     * @param  list<array{id: int, text: string, t_indices: list<int>, translatable?: bool}>  $segments
     * @param  array<int, string>  $translations
     */
    public function replaceSegments(DOMElement $scope, array $segments, array $translations): bool
    {
        if ($segments === [] || $translations === []) {
            return false;
        }

        $nodes = $this->textNodeIndex->textNodes($scope);
        if ($nodes === []) {
            return false;
        }

        $updated = false;

        foreach ($segments as $segment) {
            if (! ($segment['translatable'] ?? true)) {
                continue;
            }

            $id = (int) ($segment['id'] ?? -1);
            if (! isset($translations[$id])) {
                continue;
            }

            $indices = $segment['t_indices'] ?? [];
            if ($indices === []) {
                continue;
            }

            $text = $translations[$id];
            $firstIndex = $indices[0];
            if (! isset($nodes[$firstIndex])) {
                continue;
            }

            $originalText = (string) ($segment['text'] ?? '');
            $nodes[$firstIndex]->nodeValue = $this->escapeForXmlText($text);
            for ($i = 1, $count = count($indices); $i < $count; $i++) {
                $index = $indices[$i];
                if (isset($nodes[$index])) {
                    $nodes[$index]->nodeValue = '';
                }
            }

            if ($originalText !== '') {
                $this->clearDuplicateTextNodes($nodes, $originalText, array_flip($indices));
            }

            $updated = true;
        }

        return $updated;
    }

    /**
     * @param  list<DOMElement>  $nodes
     * @param  array<int, int>  $keepIndices
     */
    private function clearDuplicateTextNodes(array $nodes, string $originalText, array $keepIndices): void
    {
        $normalizedOriginal = $this->normalizeComparableText($originalText);

        foreach ($nodes as $index => $node) {
            if (isset($keepIndices[$index])) {
                continue;
            }

            if ($this->normalizeComparableText($node->textContent ?? '') === $normalizedOriginal) {
                $node->nodeValue = '';
            }
        }
    }

    private function normalizeComparableText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    public function replaceInParagraph(DOMElement $paragraph, string $text): bool
    {
        $textNodes = $this->textNodes($paragraph);
        if ($textNodes === []) {
            return false;
        }

        $textNodes[0]->nodeValue = $this->escapeForXmlText($text);
        for ($i = 1, $count = count($textNodes); $i < $count; $i++) {
            $textNodes[$i]->nodeValue = '';
        }

        return true;
    }

    /**
     * @param  list<string>  $cellTexts
     */
    public function replaceInTable(DOMElement $table, array $cellTexts): int
    {
        $cells = $this->tableCells($table);
        $updated = 0;

        foreach ($cells as $position => $cell) {
            if (! array_key_exists($position, $cellTexts)) {
                continue;
            }

            $text = trim($cellTexts[$position]);
            if ($text === '') {
                continue;
            }

            if ($this->replaceInParagraph($cell, $text)) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * @return list<DOMElement>
     */
    private function textNodes(DOMElement $scope): array
    {
        return $this->textNodeIndex->textNodes($scope);
    }

    /**
     * @return list<DOMElement>
     */
    private function tableCells(DOMElement $table): array
    {
        $cells = [];
        foreach (OoxmlXml::children($table, 'tr') as $row) {
            foreach (OoxmlXml::children($row, 'tc') as $cell) {
                $cells[] = $cell;
            }
        }

        return $cells;
    }

    private function escapeForXmlText(string $text): string
    {
        return str_replace(["\r\n", "\r"], "\n", $text);
    }
}

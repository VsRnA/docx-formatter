<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Marks translatable spans in block HTML for segment-aware translation updates.
 */
final class OoxmlHtmlSegmentAnnotator
{
    /**
     * @param  list<array{id: int, text: string, translatable?: bool}>  $segments
     */
    public function annotate(string $html, array $segments): string
    {
        if ($segments === [] || trim($html) === '') {
            return $html;
        }

        $document = $this->loadHtmlDocument($html);
        if ($document === null) {
            return $html;
        }

        $root = $document->documentElement;
        if ($root === null) {
            return $html;
        }

        foreach ($segments as $segment) {
            if (! ($segment['translatable'] ?? true)) {
                continue;
            }

            $text = (string) ($segment['text'] ?? '');
            if (trim($text) === '') {
                continue;
            }

            while ($this->wrapNextOccurrence($document, $root, $text, (int) $segment['id'])) {
                // Wrap every remaining occurrence of the segment text.
            }
        }

        return $this->serializeDocument($document, $root);
    }

    /**
     * @param  array<int, string>  $translations
     * @param  list<array{id: int, text: string, translatable?: bool}>  $segments
     */
    public function applyTranslations(string $html, array $translations, array $segments = []): string
    {
        if ($translations === []) {
            return $html;
        }

        $replaced = preg_replace_callback(
            '/data-ooxml-seg="(\d+)"[^>]*>(.*?)<\/span>/su',
            static function (array $matches) use ($translations): string {
                $id = (int) $matches[1];
                if (! isset($translations[$id])) {
                    return $matches[0];
                }

                return 'data-ooxml-seg="'.$id.'">'.e($translations[$id]).'</span>';
            },
            $html,
        );

        $html = is_string($replaced) ? $replaced : $html;

        foreach ($segments as $segment) {
            if (! ($segment['translatable'] ?? true)) {
                continue;
            }

            $id = (int) ($segment['id'] ?? -1);
            if (! isset($translations[$id])) {
                continue;
            }

            $original = (string) ($segment['text'] ?? '');
            $translated = $translations[$id];
            if ($original === '' || $translated === '' || $original === $translated) {
                continue;
            }

            if ($this->visibleTextContainsBoth($html, $original, $translated)) {
                $html = $this->removeUntaggedCopies($html, $original);
            }
        }

        return $html;
    }

    /**
     * @param  list<array{id: int, text: string, translatable?: bool}>  $segments
     */
    public function hasUntranslatedSegments(string $html, array $segments, array $translations): bool
    {
        foreach ($segments as $segment) {
            if (! ($segment['translatable'] ?? true)) {
                continue;
            }

            $id = (int) ($segment['id'] ?? -1);
            $original = trim((string) ($segment['text'] ?? ''));
            if ($original === '') {
                continue;
            }

            if ($this->hasUntaggedVisibleText($html, $original)) {
                return true;
            }
        }

        return false;
    }

    public function hasUntaggedVisibleText(string $html, string $needle): bool
    {
        if ($needle === '') {
            return false;
        }

        $untaggedHtml = preg_replace(
            '/<span data-ooxml-seg="[^"]*"[^>]*>.*?<\/span>/su',
            '',
            $html,
        );

        return $this->visibleTextContains(is_string($untaggedHtml) ? $untaggedHtml : $html, $needle);
    }

    public function removeUntaggedCopies(string $html, string $original): string
    {
        $needles = array_values(array_unique(array_filter([
            $original,
            e($original),
        ], static fn (string $value): bool => $value !== '')));

        if ($needles === []) {
            return $html;
        }

        $parts = preg_split(
            '/(<span data-ooxml-seg="[^"]*"[^>]*>.*?<\/span>)/su',
            $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE,
        );

        if (! is_array($parts)) {
            return $html;
        }

        $result = '';
        foreach ($parts as $index => $part) {
            if ($index % 2 === 1) {
                $result .= $part;

                continue;
            }

            $cleaned = $part;
            foreach ($needles as $needle) {
                $cleaned = str_replace($needle, '', $cleaned);
            }

            $result .= $cleaned;
        }

        return $result;
    }

    public function visibleTextContainsBoth(string $html, string $original, string $translated): bool
    {
        $visible = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');

        return $original !== ''
            && $translated !== ''
            && $original !== $translated
            && str_contains($visible, $original)
            && str_contains($visible, $translated);
    }

    public function visibleTextContains(string $html, string $needle): bool
    {
        if ($needle === '') {
            return false;
        }

        $visible = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
        $normalizedNeedle = trim(preg_replace('/\s+/u', ' ', $needle) ?? '');

        return $normalizedNeedle !== '' && str_contains($visible, $normalizedNeedle);
    }

    private function wrapNextOccurrence(DOMDocument $document, DOMElement $root, string $segmentText, int $segmentId): bool
    {
        $textNodes = $this->collectUnwrappedTextNodes($root);
        if ($textNodes === []) {
            return false;
        }

        $target = $this->normalizeMatchText($segmentText);
        if ($target === '') {
            return false;
        }

        for ($start = 0, $count = count($textNodes); $start < $count; $start++) {
            $match = $this->matchSegmentFromTextNodes($textNodes, $start, $target);
            if ($match === null) {
                continue;
            }

            $this->wrapMatchedTextNodes($document, $match['nodes'], $match['prefix_lengths'], $segmentId);

            return true;
        }

        return false;
    }

    /**
     * @param  list<DOMText>  $textNodes
     * @return array{nodes: list<DOMText>, prefix_lengths: list<int>}|null
     */
    private function matchSegmentFromTextNodes(array $textNodes, int $startIndex, string $target): ?array
    {
        $consumed = '';
        $usedNodes = [];
        $prefixLengths = [];

        for ($index = $startIndex, $count = count($textNodes); $index < $count; $index++) {
            $node = $textNodes[$index];
            $nodeText = $node->textContent ?? '';
            $usedNodes[] = $node;
            $consumed .= $nodeText;

            $normalized = $this->normalizeMatchText($consumed);
            if ($normalized === $target) {
                $prefixLengths = $this->prefixLengthsForMatch($usedNodes, $target);

                return [
                    'nodes' => $usedNodes,
                    'prefix_lengths' => $prefixLengths,
                ];
            }

            if ($normalized !== '' && ! str_starts_with($target, $normalized)) {
                break;
            }
        }

        return null;
    }

    /**
     * @param  list<DOMText>  $nodes
     * @return list<int>
     */
    private function prefixLengthsForMatch(array $nodes, string $target): array
    {
        $remaining = $target;
        $prefixLengths = [];

        foreach ($nodes as $node) {
            $nodeText = $node->textContent ?? '';
            if ($nodeText === '') {
                $prefixLengths[] = 0;

                continue;
            }

            $matchedLength = $this->matchPrefixLength($nodeText, $remaining);
            $prefixLengths[] = $matchedLength;
            $remaining = mb_substr($remaining, $matchedLength);

            if ($remaining === '') {
                break;
            }
        }

        while (count($prefixLengths) < count($nodes)) {
            $prefixLengths[] = 0;
        }

        return $prefixLengths;
    }

    private function matchPrefixLength(string $nodeText, string $target): int
    {
        $nodeLength = mb_strlen($nodeText);
        $targetLength = mb_strlen($target);

        for ($length = 1; $length <= $nodeLength; $length++) {
            $candidate = $this->normalizeMatchText(mb_substr($nodeText, 0, $length));
            if ($candidate === '') {
                continue;
            }

            if (str_starts_with($target, $candidate)) {
                if ($candidate === $target) {
                    return $length;
                }

                continue;
            }

            break;
        }

        return min($nodeLength, $targetLength);
    }

    /**
     * @param  list<DOMText>  $nodes
     * @param  list<int>  $prefixLengths
     */
    private function wrapMatchedTextNodes(DOMDocument $document, array $nodes, array $prefixLengths, int $segmentId): void
    {
        if ($nodes === []) {
            return;
        }

        $prepared = [];
        foreach ($nodes as $index => $node) {
            $prefixLength = $prefixLengths[$index] ?? mb_strlen($node->textContent ?? '');
            if ($prefixLength <= 0) {
                continue;
            }

            $prepared[] = $this->splitTextNode($document, $node, 0, $prefixLength);
        }

        if ($prepared === []) {
            return;
        }

        $span = $document->createElement('span');
        $span->setAttribute('data-ooxml-seg', (string) $segmentId);

        $first = $prepared[0];
        $parent = $first->parentNode;
        if (! $parent instanceof DOMElement) {
            return;
        }

        $parent->insertBefore($span, $first);

        foreach ($prepared as $node) {
            if ($node->parentNode === $parent) {
                $parent->removeChild($node);
            }

            $span->appendChild($node);
        }
    }

    private function splitTextNode(DOMDocument $document, DOMText $node, int $start, int $length): DOMText
    {
        $text = $node->textContent ?? '';
        $textLength = mb_strlen($text);

        if ($start <= 0 && $length >= $textLength) {
            return $node;
        }

        $before = mb_substr($text, 0, $start);
        $middle = mb_substr($text, $start, $length);
        $after = mb_substr($text, $start + $length);

        $parent = $node->parentNode;
        if (! $parent instanceof DOMElement) {
            return $node;
        }

        if ($before !== '') {
            $parent->insertBefore($document->createTextNode($before), $node);
        }

        $middleNode = $document->createTextNode($middle);
        $parent->replaceChild($middleNode, $node);

        if ($after !== '') {
            $parent->insertBefore($document->createTextNode($after), $middleNode->nextSibling);
        }

        return $middleNode;
    }

    /**
     * @return list<DOMText>
     */
    private function collectUnwrappedTextNodes(DOMElement $root): array
    {
        $nodes = [];
        $this->walkTextNodes($root, $nodes);

        return $nodes;
    }

    /**
     * @param  list<DOMText>  $nodes
     */
    private function walkTextNodes(DOMNode $node, array &$nodes): void
    {
        if ($node instanceof DOMText) {
            if ($this->isInsideSegmentSpan($node)) {
                return;
            }

            if (trim(str_replace("\u{00A0}", ' ', $node->textContent ?? '')) !== '') {
                $nodes[] = $node;
            }

            return;
        }

        if (! $node instanceof DOMElement) {
            return;
        }

        if ($node->tagName === 'script' || $node->tagName === 'style') {
            return;
        }

        if ($node->hasAttribute('data-ooxml-seg')) {
            return;
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            $this->walkTextNodes($child, $nodes);
        }
    }

    private function isInsideSegmentSpan(DOMText $node): bool
    {
        $parent = $node->parentNode;

        while ($parent instanceof DOMElement) {
            if ($parent->hasAttribute('data-ooxml-seg')) {
                return true;
            }

            $parent = $parent->parentNode;
        }

        return false;
    }

    private function normalizeMatchText(string $text): string
    {
        $normalized = str_replace("\u{00A0}", ' ', $text);
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? '';

        return trim($normalized);
    }

    private function loadHtmlDocument(string $html): ?DOMDocument
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="__ooxml_root__">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $document : null;
    }

    private function serializeDocument(DOMDocument $document, DOMElement $root): string
    {
        $serialized = '';
        foreach ($root->childNodes as $child) {
            $serialized .= $document->saveHTML($child);
        }

        return is_string($serialized) ? $serialized : '';
    }
}

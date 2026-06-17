<?php

namespace App\Infrastructure\Document\Translation;

use App\Enums\BlockType;
use App\Infrastructure\Document\BlockHtmlWrapper;

final class TranslatedHtmlPatcher
{
    public function apply(string $html, string $text, BlockType $type): string
    {
        $html = BlockHtmlWrapper::stripUnsupportedFigures($html);

        if ($this->shouldPreserveLayout($html)) {
            return $this->replaceTextInLayoutPreservingHtml($html, $text);
        }

        if (! $this->hasRichFormatting($html)) {
            return $this->replaceTextInHtml($html, $text, $type);
        }

        if ($this->hasFragmentedTextNodes($html)) {
            return $this->replaceEntireInnerHtml($html, $text);
        }

        return $this->replaceTextPreservingMarkup($html, $text);
    }

    public function shouldPreserveLayout(string $html): bool
    {
        $html = BlockHtmlWrapper::stripUnsupportedFigures($html);

        return str_contains($html, 'doc-image')
            || str_contains($html, 'doc-textbox')
            || str_contains($html, 'doc-symbol-row')
            || str_contains($html, 'doc-page-overlay')
            || str_contains($html, 'data-pending-marker');
    }

    private function replaceTextInLayoutPreservingHtml(string $html, string $text): string
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);

        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="__layout_root__">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return $html;
        }

        $root = $document->getElementById('__layout_root__');
        if ($root === null) {
            return $html;
        }

        $textNodes = [];
        $this->collectReplaceableTextNodes($root, $textNodes);

        if ($textNodes === []) {
            return $html;
        }

        $textNodes[0]->nodeValue = $text;
        for ($index = 1, $count = count($textNodes); $index < $count; $index++) {
            $textNodes[$index]->nodeValue = '';
        }

        $serialized = '';
        foreach ($root->childNodes as $child) {
            $serialized .= $document->saveHTML($child);
        }

        return is_string($serialized) ? $serialized : $html;
    }

    /**
     * @param  list<\DOMText>  $textNodes
     */
    private function collectReplaceableTextNodes(\DOMNode $node, array &$textNodes): void
    {
        if ($node instanceof \DOMText) {
            if ($this->isInsideProtectedStructure($node)) {
                return;
            }

            if (trim(str_replace("\u{00A0}", ' ', $node->textContent ?? '')) !== '') {
                $textNodes[] = $node;
            }

            return;
        }

        if (! $node instanceof \DOMElement) {
            return;
        }

        if (in_array($node->tagName, ['script', 'style', 'img'], true)) {
            return;
        }

        if ($node->hasAttribute('data-ooxml-seg')) {
            return;
        }

        if ($this->isProtectedStructureElement($node)) {
            return;
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            $this->collectReplaceableTextNodes($child, $textNodes);
        }
    }

    private function isProtectedStructureElement(\DOMElement $element): bool
    {
        $class = $element->getAttribute('class');

        return str_contains($class, 'doc-image')
            || str_contains($class, 'doc-image__unsupported')
            || str_contains($class, 'doc-page-overlay');
    }

    private function isInsideProtectedStructure(\DOMText $node): bool
    {
        $parent = $node->parentNode;

        while ($parent instanceof \DOMElement) {
            if ($this->isProtectedStructureElement($parent)) {
                return true;
            }

            if ($parent->hasAttribute('data-ooxml-seg')) {
                return true;
            }

            $parent = $parent->parentNode;
        }

        return false;
    }

    private function hasRichFormatting(string $html): bool
    {
        return (bool) preg_match('/<(strong|em|u|s|sup|sub|span)\b/i', $html);
    }

    private function hasFragmentedTextNodes(string $html): bool
    {
        if (! preg_match('#^(<(?:div|p|h[1-6]|li|td|th)(?:\s[^>]*)?>)(.*)(</(?:div|p|h[1-6]|li|td|th)>)#si', trim($html), $matches)) {
            return false;
        }

        preg_match_all('#>([^<]+)<#', $matches[2], $textNodes);
        $nonEmpty = array_filter($textNodes[1], static fn (string $value): bool => trim($value) !== '');

        return count($nonEmpty) > 1;
    }

    private function replaceEntireInnerHtml(string $html, string $text): string
    {
        $escaped = e($text);

        if (preg_match('#^(<(?:div|p|h[1-6]|li|td|th)(?:\s[^>]*)?>)(.*)(</(?:div|p|h[1-6]|li|td|th)>)#si', trim($html), $matches)) {
            return $matches[1].$escaped.$matches[3];
        }

        return '<p>'.$escaped.'</p>';
    }

    private function replaceTextPreservingMarkup(string $html, string $text): string
    {
        $escaped = e($text);

        if (preg_match('#^(<(?:div|p|h[1-6]|li|td|th)(?:\s[^>]*)?>)(.*)(</(?:div|p|h[1-6]|li|td|th)>)#si', trim($html), $matches)) {
            $open = $matches[1];
            $inner = $matches[2];
            $close = $matches[3];

            if (preg_match('/<(strong|em|u|s|sup|sub|span)\b/i', $inner)) {
                $replaced = false;
                $inner = preg_replace_callback(
                    '#>([^<]+)<#',
                    static function (array $matches) use ($escaped, &$replaced): string {
                        if ($replaced || trim($matches[1]) === '') {
                            return $matches[0];
                        }

                        $replaced = true;

                        return '>'.$escaped.'<';
                    },
                    $inner,
                ) ?? $inner;

                return $open.$inner.$close;
            }

            return $open.$escaped.$close;
        }

        return '<p>'.$escaped.'</p>';
    }

    private function replaceTextInHtml(string $html, string $text, BlockType $type): string
    {
        $escaped = e($text);

        if (preg_match('#^(<(?:div|p|h[1-6]|li)(?:\s[^>]*)?>)(.*)(</(?:div|p|h[1-6]|li)>)#si', trim($html), $matches)) {
            return $matches[1].$escaped.$matches[3];
        }

        $tag = match ($type) {
            BlockType::Heading => 'h2',
            BlockType::List => 'li',
            BlockType::Table => 'p',
            default => 'p',
        };

        return '<'.$tag.'>'.$escaped.'</'.$tag.'>';
    }
}

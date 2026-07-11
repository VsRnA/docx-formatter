<?php

namespace App\Infrastructure\Docx\Ooxml;

use DOMDocument;
use DOMElement;
use DOMXPath;

final class OoxmlXml
{
    public static function xpath(DOMDocument $document): DOMXPath
    {
        $xpath = new DOMXPath($document);
        foreach (OoxmlNamespaces::xpathMap() as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }

        return $xpath;
    }

    public static function child(DOMElement $parent, string $localName): ?DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    /** @return list<DOMElement> */
    public static function children(DOMElement $parent, string $localName): array
    {
        $result = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName) {
                $result[] = $child;
            }
        }

        return $result;
    }

    public static function attr(DOMElement $element, string $name): ?string
    {
        if ($element->hasAttribute($name)) {
            $value = $element->getAttribute($name);

            return $value === '' ? null : $value;
        }

        if ($element->hasAttributeNS(OoxmlNamespaces::W, $name)) {
            $value = $element->getAttributeNS(OoxmlNamespaces::W, $name);

            return $value === '' ? null : $value;
        }

        return null;
    }

    public static function onOff(DOMElement $parent, string $localName): bool
    {
        $node = self::child($parent, $localName);
        if (! $node instanceof DOMElement) {
            return false;
        }

        $val = self::attr($node, 'val');

        return $val === null || ! in_array(strtolower((string) $val), ['0', 'false', 'off'], true);
    }

    public static function text(DOMElement $element): string
    {
        $parts = [];
        foreach ($element->getElementsByTagNameNS(OoxmlNamespaces::W, 't') as $textNode) {
            if ($textNode instanceof DOMElement) {
                $parts[] = $textNode->textContent ?? '';
            }
        }

        return implode('', $parts);
    }

    public static function twipsToPt(?string $twips): ?float
    {
        if ($twips === null || ! is_numeric($twips)) {
            return null;
        }

        return round(((int) $twips) / 20, 1);
    }

    public static function fontFamilyFromRFonts(?DOMElement $fonts): ?string
    {
        if ($fonts === null) {
            return null;
        }

        foreach (['ascii', 'hAnsi', 'eastAsia', 'cs'] as $attribute) {
            $value = self::attr($fonts, $attribute);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    public static function sizeHalfPointsFromRPr(?DOMElement $rPr): ?string
    {
        if ($rPr === null) {
            return null;
        }

        $size = self::child($rPr, 'sz');

        return $size ? self::attr($size, 'val') : (self::child($rPr, 'szCs') ? self::attr(self::child($rPr, 'szCs'), 'val') : null);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public static function filterMeta(array $meta): array
    {
        return array_filter(
            $meta,
            static fn (mixed $value): bool => $value !== null && $value !== [],
        );
    }

    public static function serializeElement(DOMElement $element): string
    {
        return $element->ownerDocument?->saveXML($element) ?: '';
    }

    public static function symChar(?string $hexChar, ?string $font): string
    {
        if ($hexChar === null || $hexChar === '') {
            return '';
        }

        $code = hexdec($hexChar);
        if ($code <= 0) {
            return '';
        }

        try {
            return mb_chr($code, 'UTF-8');
        } catch (\ValueError) {
            return '';
        }
    }

    public static function serializeRunProperties(?DOMElement $rPr): string
    {
        if ($rPr === null) {
            return '';
        }

        return $rPr->ownerDocument?->saveXML($rPr) ?: '';
    }

    public static function normalizePlainText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * True when the element sits under mc:Fallback (VML duplicate of mc:Choice).
     * Text and textbox content there must not be collected again.
     */
    public static function isInsideMarkupCompatibilityFallback(DOMElement $element): bool
    {
        $node = $element->parentNode;

        while ($node instanceof DOMElement) {
            if ($node->localName === 'Fallback') {
                return true;
            }

            $node = $node->parentNode;
        }

        return false;
    }
}

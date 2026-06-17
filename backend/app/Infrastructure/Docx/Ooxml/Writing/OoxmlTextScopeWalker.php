<?php

namespace App\Infrastructure\Docx\Ooxml\Writing;

use App\Infrastructure\Docx\Ooxml\OoxmlNamespaces;
use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use DOMDocument;
use DOMElement;

/**
 * Walks word/document.xml body in the same order as OoxmlBodyWalker.
 *
 * @phpstan-type TextScope array{index: int, kind: 'paragraph'|'table', element: DOMElement}
 */
final class OoxmlTextScopeWalker
{
    /**
     * @return list<TextScope>
     */
    public function collect(DOMDocument $document): array
    {
        $body = $document->getElementsByTagNameNS(OoxmlNamespaces::W, 'body')->item(0);
        if (! $body instanceof DOMElement) {
            return [];
        }

        $scopes = [];
        $index = 0;

        foreach ($this->flattenBodyChildren($body) as $element) {
            $scopes = array_merge($scopes, $this->dispatch($element, $index));
        }

        return $scopes;
    }

    /**
     * @return list<TextScope>
     */
    private function dispatch(DOMElement $element, int &$index): array
    {
        return match ($element->localName) {
            'p' => [['index' => $index++, 'kind' => 'paragraph', 'element' => $element]],
            'tbl' => [['index' => $index++, 'kind' => 'table', 'element' => $element]],
            'sdt' => $this->walkSdt($element, $index),
            default => [],
        };
    }

    /**
     * @return list<DOMElement>
     */
    private function flattenBodyChildren(DOMElement $body): array
    {
        $elements = [];
        foreach ($body->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }
            if ($child->localName === 'sectPr') {
                continue;
            }
            $elements[] = $child;
        }

        return $elements;
    }

    /**
     * @return list<TextScope>
     */
    private function walkSdt(DOMElement $sdt, int &$index): array
    {
        $content = OoxmlXml::child($sdt, 'sdtContent');
        if (! $content) {
            return [];
        }

        $scopes = [];
        foreach ($content->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $scopes = array_merge($scopes, $this->dispatch($child, $index));
            }
        }

        return $scopes;
    }
}

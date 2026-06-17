<?php

namespace App\Infrastructure\Document;

use App\Domain\Document\Port\HtmlSanitizerPort;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class HtmlSanitizerService implements HtmlSanitizerPort
{
    private ?HtmlSanitizer $sanitizer = null;

    public function sanitize(string $html): string
    {
        return $this->sanitizer()->sanitize($html);
    }

    private function sanitizer(): HtmlSanitizer
    {
        if ($this->sanitizer !== null) {
            return $this->sanitizer;
        }

        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowElement('div', ['class', 'style', 'data-block-id', 'data-block-type'])
            ->allowElement('span', ['style', 'class'])
            ->allowElement('a', ['href', 'title', 'target', 'rel'])
            ->allowElement('figure', ['class', 'style', 'data-unsupported-format', 'data-pending-marker', 'data-ooxml-left', 'data-ooxml-top', 'data-ooxml-width', 'data-ooxml-height'])
            ->allowElement('figcaption', ['class', 'style'])
            ->allowElement('img', ['src', 'alt', 'title', 'width', 'height', 'style', 'data-pending'])
            ->allowElement('table', ['class', 'style', 'border', 'cellpadding', 'cellspacing'])
            ->allowElement('colgroup', [])
            ->allowElement('col', ['style', 'span'])
            ->allowElement('tr', ['style'])
            ->allowElement('td', ['colspan', 'rowspan', 'style'])
            ->allowElement('th', ['colspan', 'rowspan', 'style'])
            ->allowElement('thead', [])
            ->allowElement('tbody', [])
            ->allowElement('ul', ['class'])
            ->allowElement('ol', ['class'])
            ->allowElement('li', ['style'])
            ->allowElement('sup', [])
            ->allowElement('sub', [])
            ->allowElement('s', [])
            ->allowElement('h1', ['class', 'style'])
            ->allowElement('h2', ['class', 'style'])
            ->allowElement('h3', ['class', 'style'])
            ->allowElement('h4', ['class', 'style'])
            ->allowElement('h5', ['class', 'style'])
            ->allowElement('h6', ['class', 'style'])
            ->allowElement('p', ['class', 'style'])
            ->allowElement('br', [])
            ->allowElement('strong', [])
            ->allowElement('em', [])
            ->allowElement('u', [])
            ->allowElement('svg', ['class', 'style', 'viewBox', 'xmlns', 'aria-hidden', 'width', 'height'])
            ->allowElement('defs', [])
            ->allowElement('marker', ['id', 'viewBox', 'refX', 'refY', 'markerWidth', 'markerHeight', 'orient', 'markerUnits'])
            ->allowElement('path', ['d', 'fill'])
            ->allowElement('line', ['x1', 'y1', 'x2', 'y2', 'stroke', 'stroke-width', 'marker-start', 'marker-end']);

        $this->sanitizer = new HtmlSanitizer($config);

        return $this->sanitizer;
    }
}

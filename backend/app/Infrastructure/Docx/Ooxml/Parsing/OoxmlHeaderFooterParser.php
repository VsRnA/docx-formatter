<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing;

use App\Infrastructure\Docx\Ooxml\OoxmlNamespaces;
use App\Infrastructure\Docx\Ooxml\OoxmlPackage;
use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use DOMDocument;
use DOMElement;

/**
 * Parses word/header*.xml and word/footer*.xml into HTML fragments.
 */
final class OoxmlHeaderFooterParser
{
    public function __construct(
        private readonly OoxmlRunParser $runs,
    ) {}

    /**
     * @return array{
     *     default: ?string,
     *     even: ?string,
     *     first: ?string
     * }
     */
    public function parseFooters(OoxmlPackage $package): array
    {
        return $this->parseParts($package, $package->footerParts());
    }

    /**
     * @return array{
     *     default: ?string,
     *     even: ?string,
     *     first: ?string
     * }
     */
    public function parseHeaders(OoxmlPackage $package): array
    {
        return $this->parseParts($package, $package->headerParts());
    }

    /**
     * @param  list<array{type: string, document: DOMDocument}>  $parts
     * @return array{default: ?string, even: ?string, first: ?string}
     */
    private function parseParts(OoxmlPackage $package, array $parts): array
    {
        $result = [
            'default' => null,
            'even' => null,
            'first' => null,
        ];

        foreach ($parts as $part) {
            $type = $part['type'];
            $html = $this->renderPart($part['document'], $package);
            if ($html === '') {
                continue;
            }

            if (isset($result[$type])) {
                $result[$type] = trim(($result[$type] ?? '').' '.$html);
            }
        }

        return $result;
    }

    private function renderPart(DOMDocument $document, OoxmlPackage $package): string
    {
        $parts = [];

        foreach ($document->getElementsByTagNameNS(OoxmlNamespaces::W, 'p') as $paragraph) {
            if (! $paragraph instanceof DOMElement) {
                continue;
            }

            $pendingImages = null;
            $parsed = $this->runs->parseContainer($paragraph, null, $package, $pendingImages);
            if ($parsed['html'] !== '') {
                $parts[] = $parsed['html'];
            }
        }

        return trim(implode(' ', $parts));
    }
}

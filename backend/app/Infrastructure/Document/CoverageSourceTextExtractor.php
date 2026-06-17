<?php

namespace App\Infrastructure\Document;

use App\Infrastructure\Docx\Ooxml\OoxmlNamespaces;
use App\Infrastructure\Docx\Ooxml\OoxmlPackage;
use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use DOMElement;
use ZipArchive;

final class CoverageSourceTextExtractor
{
    /**
     * @return list<string>
     */
    public function extractFragments(string $localDocxPath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($localDocxPath) !== true) {
            return [];
        }

        $entries = [
            'word/document.xml',
            'word/footnotes.xml',
            'word/endnotes.xml',
        ];

        $fragments = [];
        foreach ($entries as $entry) {
            $xml = $zip->getFromName($entry);
            if (! is_string($xml) || $xml === '') {
                continue;
            }

            $document = new \DOMDocument;
            $document->preserveWhiteSpace = false;
            if (! @$document->loadXML($xml)) {
                continue;
            }

            $root = $document->documentElement;
            if ($root instanceof DOMElement) {
                $fragments = array_merge($fragments, $this->collectTranslatableFragments($root));
            }
        }

        foreach ($this->headerFooterEntries($zip) as $xml) {
            $document = new \DOMDocument;
            $document->preserveWhiteSpace = false;
            if (! @$document->loadXML($xml)) {
                continue;
            }

            $root = $document->documentElement;
            if ($root instanceof DOMElement) {
                $fragments = array_merge($fragments, $this->collectTranslatableFragments($root));
            }
        }

        $zip->close();

        return array_values(array_filter(array_unique($fragments)));
    }

    public function extract(string $localDocxPath): string
    {
        return OoxmlXml::normalizePlainText(implode(' ', $this->extractFragments($localDocxPath)));
    }

    /**
     * @param  list<string>  $sourceFragments
     * @return list<string>
     */
    public function findMissingFragments(array $sourceFragments, string $blocksPlain): array
    {
        $normalizedBlocks = OoxmlXml::normalizePlainText($blocksPlain);
        $missing = [];

        foreach ($sourceFragments as $fragment) {
            if ($fragment === '') {
                continue;
            }

            if (! str_contains($normalizedBlocks, $fragment)) {
                $missing[] = $fragment;
            }
        }

        return $missing;
    }

    /**
     * @return list<string>
     */
    private function collectTranslatableFragments(DOMElement $element): array
    {
        $fragments = [];

        foreach ($element->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if ($child->localName === 'del') {
                continue;
            }

            if ($child->localName === 'ins') {
                $fragments = array_merge($fragments, $this->collectTranslatableFragments($child));

                continue;
            }

            if ($child->namespaceURI === OoxmlNamespaces::M
                && in_array($child->localName, ['oMath', 'oMathPara'], true)) {
                continue;
            }

            if (in_array($child->localName, ['instrText', 'delInstrText', 'fldChar'], true)) {
                continue;
            }

            if ($child->localName === 't' && $child->namespaceURI === OoxmlNamespaces::W) {
                $text = OoxmlXml::normalizePlainText($child->textContent ?? '');
                if ($text !== '') {
                    $fragments[] = $text;
                }

                continue;
            }

            $fragments = array_merge($fragments, $this->collectTranslatableFragments($child));
        }

        return $fragments;
    }

    /**
     * @return list<string>
     */
    private function headerFooterEntries(ZipArchive $zip): array
    {
        $xmlParts = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (! is_string($name)) {
                continue;
            }

            if (! preg_match('#^word/(header|footer)\d+\.xml$#', $name)) {
                continue;
            }

            $xml = $zip->getFromName($name);
            if (is_string($xml) && $xml !== '') {
                $xmlParts[] = $xml;
            }
        }

        return $xmlParts;
    }
}

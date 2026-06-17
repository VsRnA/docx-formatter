<?php

namespace App\Infrastructure\Docx\Ooxml;

use DOMDocument;
use DOMElement;
use RuntimeException;
use ZipArchive;

/**
 * Read-only access to DOCX (OOXML zip) parts.
 */
final class OoxmlPackage
{
    private ZipArchive $zip;

    /** @var array<string, string> */
    private array $relationships = [];

    public function __construct(
        private readonly string $path,
    ) {
        $this->zip = new ZipArchive;
        if ($this->zip->open($this->path) !== true) {
            throw new RuntimeException('Unable to open DOCX archive: '.$this->path);
        }

        $this->relationships = $this->loadRelationships('word/_rels/document.xml.rels');
    }

    public function document(): DOMDocument
    {
        return $this->loadXml('word/document.xml');
    }

    public function styles(): ?DOMDocument
    {
        return $this->hasEntry('word/styles.xml') ? $this->loadXml('word/styles.xml') : null;
    }

    public function numbering(): ?DOMDocument
    {
        return $this->hasEntry('word/numbering.xml') ? $this->loadXml('word/numbering.xml') : null;
    }

    public function footnotes(): ?DOMDocument
    {
        return $this->hasEntry('word/footnotes.xml') ? $this->loadXml('word/footnotes.xml') : null;
    }

    public function endnotes(): ?DOMDocument
    {
        return $this->hasEntry('word/endnotes.xml') ? $this->loadXml('word/endnotes.xml') : null;
    }

    /**
     * @return list<array{type: string, document: DOMDocument}>
     */
    public function footerParts(): array
    {
        return $this->loadSectionParts('footer');
    }

    /**
     * @return list<array{type: string, document: DOMDocument}>
     */
    public function headerParts(): array
    {
        return $this->loadSectionParts('header');
    }

    /**
     * @return list<array{type: string, document: DOMDocument}>
     */
    private function loadSectionParts(string $kind): array
    {
        $parts = [];
        $seen = [];

        foreach ($this->sectionPartReferences($kind) as $reference) {
            $entry = $reference['entry'];
            if (isset($seen[$entry])) {
                continue;
            }

            $seen[$entry] = true;
            if (! $this->hasEntry($entry)) {
                continue;
            }

            $parts[] = [
                'type' => $reference['type'],
                'document' => $this->loadXml($entry),
            ];
        }

        return $parts;
    }

    /**
     * @return list<array{type: string, entry: string}>
     */
    private function sectionPartReferences(string $kind): array
    {
        $references = [];
        $document = $this->document();
        $body = $document->getElementsByTagNameNS(OoxmlNamespaces::W, 'body')->item(0);

        if ($body instanceof DOMElement) {
            $sectPr = OoxmlXml::child($body, 'sectPr');
            if ($sectPr instanceof DOMElement) {
                foreach ($sectPr->childNodes as $child) {
                    if (! $child instanceof DOMElement) {
                        continue;
                    }

                    if ($child->localName !== $kind.'Reference') {
                        continue;
                    }

                    $relationshipId = $child->getAttributeNS(OoxmlNamespaces::R, 'id');
                    if ($relationshipId === '') {
                        $relationshipId = OoxmlXml::attr($child, 'id') ?? '';
                    }

                    $entry = $this->resolveMediaPath($relationshipId);
                    if ($entry === null) {
                        continue;
                    }

                    $references[] = [
                        'type' => OoxmlXml::attr($child, 'type') ?? 'default',
                        'entry' => $entry,
                    ];
                }
            }
        }

        if ($references !== []) {
            return $references;
        }

        foreach ($this->relationships as $target) {
            $basename = basename($target);
            if (! str_starts_with($basename, $kind)) {
                continue;
            }

            $references[] = [
                'type' => 'default',
                'entry' => $this->normalizeEntryPath($target),
            ];
        }

        return $references;
    }

    public function resolveMediaPath(string $relationshipId): ?string
    {
        $target = $this->relationships[$relationshipId] ?? null;
        if ($target === null) {
            return null;
        }

        return $this->normalizeEntryPath($target);
    }

    public function resolveMediaExtension(string $relationshipId): ?string
    {
        $entry = $this->resolveMediaPath($relationshipId);
        if ($entry === null) {
            return null;
        }

        $extension = strtolower(pathinfo($entry, PATHINFO_EXTENSION));

        return $extension !== '' ? $extension : null;
    }

    private function normalizeEntryPath(string $target): string
    {
        if (str_starts_with($target, '/')) {
            return ltrim($target, '/');
        }

        $path = str_starts_with($target, 'word/') ? $target : 'word/'.$target;
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..' && $segments !== []) {
                array_pop($segments);

                continue;
            }
            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    public function extractEntryTo(string $entry, string $destination): bool
    {
        $contents = $this->zip->getFromName($entry);
        if ($contents === false) {
            return false;
        }

        return file_put_contents($destination, $contents) !== false;
    }

    public function close(): void
    {
        $this->zip->close();
    }

    private function hasEntry(string $name): bool
    {
        return $this->zip->locateName($name) !== false;
    }

    private function loadXml(string $entry): DOMDocument
    {
        $raw = $this->zip->getFromName($entry);
        if ($raw === false) {
            throw new RuntimeException('Missing OOXML entry: '.$entry);
        }

        $document = new DOMDocument;
        $document->preserveWhiteSpace = false;
        $document->formatOutput = false;
        if (! @$document->loadXML($raw)) {
            throw new RuntimeException('Invalid XML in '.$entry);
        }

        return $document;
    }

    /**
     * @return array<string, string>
     */
    private function loadRelationships(string $relsPath): array
    {
        if (! $this->hasEntry($relsPath)) {
            return [];
        }

        $rels = $this->loadXml($relsPath);
        $map = [];
        foreach ($rels->getElementsByTagName('Relationship') as $rel) {
            if (! $rel instanceof DOMElement) {
                continue;
            }
            $id = OoxmlXml::attr($rel, 'Id');
            $target = OoxmlXml::attr($rel, 'Target');
            if ($id && $target) {
                $map[$id] = $target;
            }
        }

        return $map;
    }
}

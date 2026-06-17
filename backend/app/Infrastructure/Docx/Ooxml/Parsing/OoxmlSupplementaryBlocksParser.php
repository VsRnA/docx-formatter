<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing;

use App\Domain\Docx\Entity\ParsedBlock;
use App\Domain\Docx\ValueObject\ParseContext;
use App\Infrastructure\Docx\Ooxml\OoxmlNamespaces;
use App\Infrastructure\Docx\Ooxml\OoxmlPackage;
use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use DOMDocument;
use DOMElement;

/**
 * Parses headers, footers, footnotes and endnotes into translatable blocks.
 */
final class OoxmlSupplementaryBlocksParser
{
    public function __construct(
        private readonly OoxmlBodyWalker $walker,
    ) {}

    /**
     * @return list<ParsedBlock>
     */
    public function parseAll(OoxmlPackage $package, ParseContext $context): array
    {
        $blocks = [];
        $blocks = array_merge($blocks, $this->parseHeaderParts($package, $context));
        $blocks = array_merge($blocks, $this->parseFooterParts($package, $context));
        $blocks = array_merge($blocks, $this->parseNotes($package, $package->footnotes(), 'footnote', $context));
        $blocks = array_merge($blocks, $this->parseNotes($package, $package->endnotes(), 'endnote', $context));

        return $blocks;
    }

    /**
     * @return list<ParsedBlock>
     */
    public function parseHeaderParts(OoxmlPackage $package, ParseContext $context): array
    {
        return $this->parseSectionParts($package, $package->headerParts(), 'header', $context);
    }

    /**
     * @return list<ParsedBlock>
     */
    public function parseFooterParts(OoxmlPackage $package, ParseContext $context): array
    {
        return $this->parseSectionParts($package, $package->footerParts(), 'footer', $context);
    }

    /**
     * @return list<ParsedBlock>
     */
    public function parseFootnotesAndEndnotes(OoxmlPackage $package, ParseContext $context): array
    {
        return array_merge(
            $this->parseNotes($package, $package->footnotes(), 'footnote', $context),
            $this->parseNotes($package, $package->endnotes(), 'endnote', $context),
        );
    }

    /**
     * @param  list<array{type: string, document: DOMDocument}>  $parts
     * @return list<ParsedBlock>
     */
    private function parseSectionParts(OoxmlPackage $package, array $parts, string $region, ParseContext $context): array
    {
        $blocks = [];
        foreach ($parts as $part) {
            $root = $this->sectionRoot($part['document'], $region);
            if (! $root instanceof DOMElement) {
                continue;
            }

            foreach ($this->walker->walkChildren($root, $package, $context) as $block) {
                $blocks[] = $this->withRegion($block, $region, $part['type']);
            }
        }

        return $blocks;
    }

    /**
     * @return list<ParsedBlock>
     */
    private function parseNotes(
        OoxmlPackage $package,
        ?DOMDocument $document,
        string $kind,
        ParseContext $context,
    ): array {
        if ($document === null) {
            return [];
        }

        $blocks = [];
        $tag = $kind === 'footnote' ? 'footnote' : 'endnote';

        foreach ($document->getElementsByTagNameNS(OoxmlNamespaces::W, $tag) as $note) {
            if (! $note instanceof DOMElement) {
                continue;
            }

            $type = OoxmlXml::attr($note, 'type');
            if (in_array($type, ['separator', 'continuationSeparator'], true)) {
                continue;
            }

            $id = OoxmlXml::attr($note, 'id');
            if ($id === null || (int) $id <= 0) {
                continue;
            }

            foreach ($this->walker->walkChildren($note, $package, $context) as $block) {
                $blocks[] = $this->withNoteMeta($block, $kind, $id);
            }
        }

        return $blocks;
    }

    private function sectionRoot(DOMDocument $document, string $region): ?DOMElement
    {
        $localName = $region === 'header' ? 'hdr' : 'ftr';
        $root = $document->getElementsByTagNameNS(OoxmlNamespaces::W, $localName)->item(0);

        return $root instanceof DOMElement ? $root : null;
    }

    private function withRegion(ParsedBlock $block, string $region, string $sectionType): ParsedBlock
    {
        return new ParsedBlock(
            type: $block->type,
            sort: $block->sort,
            html: $block->html,
            textOriginal: $block->textOriginal,
            styles: $block->styles,
            meta: array_merge($block->meta ?? [], [
                'region' => $region,
                'section_type' => $sectionType,
            ]),
            assets: $block->assets,
            localImagePath: $block->localImagePath,
            contentJson: $block->contentJson,
        );
    }

    private function withNoteMeta(ParsedBlock $block, string $kind, string $id): ParsedBlock
    {
        return new ParsedBlock(
            type: $block->type,
            sort: $block->sort,
            html: $block->html,
            textOriginal: $block->textOriginal,
            styles: $block->styles,
            meta: array_merge($block->meta ?? [], [
                'region' => $kind,
                $kind.'_id' => $id,
            ]),
            assets: $block->assets,
            localImagePath: $block->localImagePath,
            contentJson: $block->contentJson,
        );
    }
}

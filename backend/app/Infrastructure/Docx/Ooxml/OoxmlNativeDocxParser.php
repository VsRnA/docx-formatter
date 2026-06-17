<?php

namespace App\Infrastructure\Docx\Ooxml;

use App\Domain\Docx\Entity\ParsedDocument;
use App\Domain\Docx\Service\DocumentAssembler;
use App\Domain\Docx\ValueObject\ParseContext;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlBodyWalker;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlAnchorLayoutParser;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlHeaderFooterParser;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlSectionPropertiesParser;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlSupplementaryBlocksParser;
use App\Infrastructure\Docx\Ooxml\Styles\OoxmlNumberingResolver;
use App\Infrastructure\Docx\Ooxml\Styles\OoxmlStyleResolver;

/**
 * Native OOXML parser — reads word/document.xml directly (no PhpWord).
 */
final class OoxmlNativeDocxParser
{
    public function __construct(
        private readonly OoxmlBodyWalker $walker,
        private readonly OoxmlStyleResolver $styles,
        private readonly OoxmlNumberingResolver $numbering,
        private readonly DocumentAssembler $assembler,
        private readonly OoxmlSectionPropertiesParser $sections,
        private readonly OoxmlHeaderFooterParser $headerFooters,
        private readonly OoxmlSupplementaryBlocksParser $supplementary,
        private readonly OoxmlAnchorLayoutParser $anchors,
    ) {}

    public function parse(string $localPath): ParsedDocument
    {
        $context = new ParseContext;
        $package = new OoxmlPackage($localPath);

        try {
            $document = $package->document();
            $this->styles->load($package->styles());
            $this->numbering->load($package->numbering());
            $section = $this->sections->parseDocument($document);
            if (is_array($section)) {
                $this->anchors->configurePageMarginsMm(
                    (float) $section['margin_left_mm'],
                    (float) $section['margin_top_mm'],
                );
            } else {
                $this->anchors->resetPageMargins();
            }
            $blocks = array_merge(
                $this->supplementary->parseHeaderParts($package, $context),
                $this->walker->walk($document, $package, $context),
                $this->supplementary->parseFooterParts($package, $context),
                $this->supplementary->parseFootnotesAndEndnotes($package, $context),
            );
            $headers = $this->headerFooters->parseHeaders($package);
            $footers = $this->headerFooters->parseFooters($package);
        } finally {
            $package->close();
        }

        $assembled = $this->assembler->assemble(
            basename($localPath, '.docx'),
            $blocks,
            $context,
        );

        return new ParsedDocument(
            title: $assembled->title,
            blocks: $assembled->blocks,
            meta: array_merge($assembled->meta ?? [], [
                'parser' => 'ooxml_native',
                'section' => $section,
                'defaults' => $this->styles->documentDefaults(),
                'headers' => $headers,
                'footers' => $footers,
            ]),
        );
    }
}

<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing;

use App\Domain\Docx\Entity\ParsedBlock;
use App\Infrastructure\Docx\Ooxml\OoxmlNamespaces;
use App\Infrastructure\Docx\Ooxml\OoxmlPackage;
use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use App\Infrastructure\Docx\Ooxml\Parsing\Run\OoxmlMathRenderer;
use App\Domain\Docx\ValueObject\ParseContext;
use App\Support\Constants\OoxmlTags;
use DOMDocument;
use DOMElement;

final class OoxmlBodyWalker
{
    public function __construct(
        private readonly OoxmlParagraphParser $paragraphs,
        private readonly OoxmlTableParser $tables,
        private readonly OoxmlFallbackBlockFactory $fallbacks,
        private readonly OoxmlMathRenderer $math,
    ) {}

    /**
     * @return list<ParsedBlock>
     */
    public function walk(DOMDocument $document, OoxmlPackage $package, ParseContext $context): array
    {
        $body = $document->getElementsByTagNameNS(OoxmlNamespaces::W, OoxmlTags::BODY)->item(0);
        if (! $body instanceof DOMElement) {
            $context->warn('missing_body', 'word/document.xml has no w:body');

            return [];
        }

        $blocks = [];
        foreach ($this->flattenBodyChildren($body) as $element) {
            $blocks = array_merge($blocks, $this->dispatch($element, $package, $context));
        }

        return $blocks;
    }

    /**
     * @return list<ParsedBlock>
     */
    public function walkChildren(DOMElement $container, OoxmlPackage $package, ParseContext $context): array
    {
        $blocks = [];
        foreach ($container->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }
            if ($child->localName === OoxmlTags::SECTION_PROPS) {
                continue;
            }
            $blocks = array_merge($blocks, $this->dispatch($child, $package, $context));
        }

        return $blocks;
    }

    /**
     * @return list<ParsedBlock>
     */
    private function dispatch(DOMElement $element, OoxmlPackage $package, ParseContext $context): array
    {
        if ($this->math->isMathElement($element)) {
            return [
                $this->math->createBlock($element, $context, $context->nextOoxmlScopeIndex()),
            ];
        }

        return match ($element->localName) {
            OoxmlTags::PARAGRAPH => $this->paragraphs->parse(
                $element,
                $package,
                $context,
                $context->nextOoxmlScopeIndex(),
            ),
            OoxmlTags::TABLE => $this->tables->parse(
                $element,
                $package,
                $context,
                $context->nextOoxmlScopeIndex(),
            ),
            OoxmlTags::SDT => $this->walkSdt($element, $package, $context),
            OoxmlTags::INSERT => $this->walkRevisionContainer($element, $package, $context),
            OoxmlTags::DELETE => [],
            OoxmlTags::BOOKMARK_START, OoxmlTags::BOOKMARK_END => [],
            default => [$this->fallbacks->create($element, $context, $context->nextOoxmlScopeIndex())],
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
            if ($child->localName === OoxmlTags::SECTION_PROPS) {
                continue;
            }
            $elements[] = $child;
        }

        return $elements;
    }

    /**
     * @return list<ParsedBlock>
     */
    private function walkSdt(DOMElement $sdt, OoxmlPackage $package, ParseContext $context): array
    {
        $content = OoxmlXml::child($sdt, OoxmlTags::SDT_CONTENT);
        if (! $content) {
            return [$this->fallbacks->create($sdt, $context, $context->nextOoxmlScopeIndex())];
        }

        $blocks = [];
        foreach ($content->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $blocks = array_merge($blocks, $this->dispatch($child, $package, $context));
            }
        }

        return $blocks !== [] ? $blocks : [$this->fallbacks->create($sdt, $context, $context->nextOoxmlScopeIndex())];
    }

    /**
     * @return list<ParsedBlock>
     */
    private function walkRevisionContainer(DOMElement $container, OoxmlPackage $package, ParseContext $context): array
    {
        $blocks = [];
        foreach ($container->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $blocks = array_merge($blocks, $this->dispatch($child, $package, $context));
            }
        }

        return $blocks !== [] ? $blocks : [$this->fallbacks->create($container, $context, $context->nextOoxmlScopeIndex())];
    }
}

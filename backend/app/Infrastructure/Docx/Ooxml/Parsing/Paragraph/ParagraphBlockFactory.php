<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing\Paragraph;

use App\Domain\Docx\Entity\ParsedBlock;
use App\Domain\Docx\ValueObject\BlockType;
use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use App\Infrastructure\Docx\Ooxml\Styles\OoxmlNumberingResolver;
use App\Infrastructure\Docx\Ooxml\Styles\OoxmlStyleResolver;
use App\Infrastructure\Docx\Ooxml\Writing\OoxmlTextSegmentCollector;
use App\Infrastructure\Docx\Ooxml\Parsing\Layout\ParagraphLayoutHelper;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlHtmlSegmentAnnotator;
use App\Domain\Docx\ValueObject\ParseContext;
use DOMElement;

final class ParagraphBlockFactory
{
    public function __construct(
        private readonly OoxmlStyleResolver $styles,
        private readonly OoxmlNumberingResolver $numbering,
        private readonly OoxmlTextSegmentCollector $segments,
        private readonly OoxmlHtmlSegmentAnnotator $segmentHtml,
        private readonly ParagraphLayoutHelper $layout,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $pendingImages
     */
    public function make(
        ParseContext $context,
        DOMElement $paragraph,
        string $tag,
        BlockType $type,
        string $attrString,
        string $innerHtml,
        string $plain,
        ?string $styleId,
        ?string $numId,
        ?string $ilvl,
        bool $isList,
        ?array $stylesJson,
        array $pendingImages,
        bool $pageBreakBefore = false,
        int $ooxmlScopeIndex = 0,
    ): ParsedBlock {
        $tag = $this->layout->resolveWrapperTag($tag, $innerHtml);
        $html = '<'.$tag.$attrString.'>'.$innerHtml.'</'.$tag.'>';
        $segmentList = $this->segments->collectFromParagraph($paragraph);
        $html = $this->segmentHtml->annotate($html, $segmentList);

        return new ParsedBlock(
            type: $type,
            sort: $context->nextSort(),
            html: $html,
            textOriginal: $plain !== '' ? $plain : null,
            styles: $stylesJson,
            meta: OoxmlXml::filterMeta([
                'source' => 'ooxml',
                'ooxml_scope_index' => $ooxmlScopeIndex,
                'ooxml_segments' => $segmentList !== [] ? $segmentList : null,
                'paragraph_style' => $this->styles->resolveStyleName($styleId),
                'list_num_id' => $numId,
                'list_level' => $ilvl,
                'list_marker' => $isList ? $this->numbering->resolveMarker($numId, $ilvl) : null,
                'pending_images' => $pendingImages !== [] ? $pendingImages : null,
                'page_break_before' => $pageBreakBefore ? true : null,
            ]),
        );
    }
}

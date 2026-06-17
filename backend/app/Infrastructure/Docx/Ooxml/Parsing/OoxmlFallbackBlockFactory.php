<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing;

use App\Domain\Docx\Entity\ParsedBlock;
use App\Domain\Docx\ValueObject\BlockType;
use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use App\Domain\Docx\ValueObject\ParseContext;
use App\Support\Constants\HtmlCssClasses;
use DOMElement;

final class OoxmlFallbackBlockFactory
{
    public function create(DOMElement $element, ParseContext $context, int $ooxmlScopeIndex): ParsedBlock
    {
        $localName = $element->localName;
        $plain = OoxmlXml::normalizePlainText(OoxmlXml::text($element));
        $fragment = OoxmlXml::serializeElement($element);
        $display = $plain !== '' ? htmlspecialchars($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8') : '[unsupported: '.$localName.']';

        return new ParsedBlock(
            type: BlockType::HtmlRaw,
            sort: $context->nextSort(),
            html: '<div class="'.HtmlCssClasses::DOC_RAW_OOXML.'" data-ooxml-tag="'
                .htmlspecialchars($localName, ENT_QUOTES | ENT_HTML5, 'UTF-8').'">'.$display.'</div>',
            textOriginal: $plain !== '' ? $plain : null,
            contentJson: [
                'kind' => 'ooxml_fallback',
                'localName' => $localName,
            ],
            meta: OoxmlXml::filterMeta([
                'source' => 'ooxml_fallback',
                'ooxml_scope_index' => $ooxmlScopeIndex,
                'ooxml_fragment' => $fragment !== '' ? $fragment : null,
                'confidence' => 0,
                'needs_review' => true,
            ]),
        );
    }
}

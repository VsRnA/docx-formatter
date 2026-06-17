<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing\Run;

use App\Domain\Docx\Entity\ParsedBlock;
use App\Domain\Docx\ValueObject\BlockType;
use App\Domain\Docx\ValueObject\ParseContext;
use App\Infrastructure\Docx\Ooxml\OoxmlNamespaces;
use App\Support\Constants\HtmlCssClasses;
use DOMElement;

final class OoxmlMathRenderer
{
    public function isMathElement(DOMElement $element): bool
    {
        return $element->namespaceURI === OoxmlNamespaces::M
            && in_array($element->localName, ['oMath', 'oMathPara'], true);
    }

    public function extractPlainText(DOMElement $mathRoot): string
    {
        $parts = [];
        foreach ($mathRoot->getElementsByTagNameNS(OoxmlNamespaces::M, 't') as $textNode) {
            if ($textNode instanceof DOMElement) {
                $parts[] = $textNode->textContent ?? '';
            }
        }

        return implode('', $parts);
    }

    public function renderInline(DOMElement $mathRoot): string
    {
        $text = $this->extractPlainText($mathRoot);
        $display = $text !== ''
            ? htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : '…';

        return '<span class="'.HtmlCssClasses::DOC_FORMULA.'" data-doc-formula="1">'.$display.'</span>';
    }

    public function createBlock(DOMElement $mathRoot, ParseContext $context, int $ooxmlScopeIndex): ParsedBlock
    {
        return new ParsedBlock(
            type: BlockType::Formula,
            sort: $context->nextSort(),
            html: '<div class="'.HtmlCssClasses::DOC_FORMULA_BLOCK.'" data-doc-formula="1">'.$this->renderInline($mathRoot).'</div>',
            textOriginal: null,
            contentJson: [
                'kind' => 'formula',
                'text' => $this->extractPlainText($mathRoot),
            ],
            meta: [
                'source' => 'ooxml_math',
                'ooxml_scope_index' => $ooxmlScopeIndex,
                'non_translatable' => true,
            ],
        );
    }
}

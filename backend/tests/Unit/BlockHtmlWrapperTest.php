<?php

namespace Tests\Unit;

use App\Infrastructure\Document\BlockHtmlWrapper;
use Tests\TestCase;

class BlockHtmlWrapperTest extends TestCase
{
    public function test_wraps_block_with_editor_classes_and_repairs_invalid_paragraph_wrapper(): void
    {
        $html = BlockHtmlWrapper::wrap(
            'block-1',
            'paragraph',
            '<p style="text-align:center"><div class="doc-anchored-canvas">Callout</div></p>',
            ['page_break_before' => true],
        );

        $this->assertStringContainsString('class="doc-block doc-flow-block doc-block--page-break-before doc-flow-block--page-break-before"', $html);
        $this->assertStringContainsString('data-block-id="block-1"', $html);
        $this->assertStringContainsString('<div style="text-align:center"><div class="doc-anchored-canvas">Callout</div></div>', $html);
        $this->assertStringNotContainsString('<p style="text-align:center"><div', $html);
    }

    public function test_unwraps_document_root_article(): void
    {
        $inner = '<div class="doc-block doc-flow-block">Body</div>';
        $wrapped = '<article class="document-root">'."\n".$inner."\n".'</article>';

        $this->assertSame($inner, BlockHtmlWrapper::unwrapDocumentRoot($wrapped));
    }

    public function test_strips_absolute_positioning_from_symbol_row_textboxes(): void
    {
        $html = BlockHtmlWrapper::sanitizeBlockInnerHtml(
            '<div class="doc-paragraph--symbols">'
            .'<div class="doc-symbol-row">'
            .'<div class="doc-symbol-icons"><figure class="doc-image"></figure></div>'
            .'<div class="doc-textbox doc-textbox--anchored" style="position:absolute; z-index:2; left:84px; top:55px">'
            .'<span>D</span><span>o not use the appliance.</span>'
            .'</div></div></div>',
        );

        $this->assertStringNotContainsString('doc-textbox--anchored', $html);
        $this->assertStringNotContainsString('position:absolute', $html);
        $this->assertStringNotContainsString('left:84px', $html);
        $this->assertStringContainsString('Do not use the appliance.', strip_tags($html));
    }

    public function test_strips_unsupported_emf_figures_from_stored_html(): void
    {
        $html = BlockHtmlWrapper::sanitizeBlockInnerHtml(
            '<div><figure class="doc-image doc-image--inline doc-image--unsupported" data-unsupported-format="emf">'
            .'<span class="doc-image__unsupported-icon">EMF</span>'
            .'<figcaption class="doc-image__unsupported-caption">Изображение EMF (формат не поддерживается браузером)</figcaption>'
            .'</figure><span>Warning! Due to the high risk.</span></div>',
        );

        $this->assertStringNotContainsString('doc-image--unsupported', $html);
        $this->assertStringNotContainsString('EMF', strip_tags($html));
        $this->assertStringContainsString('Warning! Due to the high risk.', strip_tags($html));
    }
}

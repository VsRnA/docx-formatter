<?php

namespace Tests\Unit;

use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlHtmlSegmentAnnotator;
use PHPUnit\Framework\TestCase;

final class OoxmlHtmlSegmentAnnotatorTest extends TestCase
{
    public function test_annotate_wraps_every_duplicate_occurrence(): void
    {
        $html = '<div class="doc-symbol-row">'
            .'<span class="doc-textbox"><span>Keep away</span></span>'
            .'<span>Keep away</span>'
            .'</div>';

        $annotator = new OoxmlHtmlSegmentAnnotator;
        $result = $annotator->annotate($html, [
            ['id' => 0, 'text' => 'Keep away', 'translatable' => true],
        ]);

        $this->assertSame(2, substr_count($result, 'data-ooxml-seg="0"'));
    }

    public function test_apply_translations_removes_untagged_duplicate_original(): void
    {
        $html = '<div class="doc-symbol-row">'
            .'<span class="doc-textbox"><span data-ooxml-seg="0">Keep away</span></span>'
            .'<span>Keep away</span>'
            .'</div>';

        $annotator = new OoxmlHtmlSegmentAnnotator;
        $result = $annotator->applyTranslations(
            $html,
            [0 => 'Держитесь подальше'],
            [
                ['id' => 0, 'text' => 'Keep away', 'translatable' => true],
            ],
        );

        $this->assertStringContainsString('Держитесь подальше', $result);
        $this->assertStringNotContainsString('Keep away', $result);
        $this->assertSame(1, substr_count($result, 'Держитесь подальше'));
    }

    public function test_annotate_wraps_text_split_by_inline_tags(): void
    {
        $html = '<p><span><strong>INTENDED</strong></span><span><strong> USE</strong></span></p>';

        $annotator = new OoxmlHtmlSegmentAnnotator;
        $result = $annotator->annotate($html, [
            ['id' => 0, 'text' => 'INTENDED USE', 'translatable' => true],
        ]);

        $this->assertStringContainsString('data-ooxml-seg="0"', $result);
        $this->assertStringContainsString('<strong>', $result);
    }

    public function test_annotate_wraps_text_with_leader_dots_in_same_paragraph(): void
    {
        $dots = "\u{2003}………………..……………….………9";
        $html = '<p><span style="font-size:14pt">SECTION 3 GENERAL IDENTIFICATION </span>'
            .'<span style="font-size:14pt">'.$dots.'</span></p>';

        $annotator = new OoxmlHtmlSegmentAnnotator;
        $result = $annotator->annotate($html, [
            ['id' => 0, 'text' => 'SECTION 3 GENERAL IDENTIFICATION', 'translatable' => true],
            ['id' => 1, 'text' => $dots, 'translatable' => false],
        ]);

        $this->assertStringContainsString('data-ooxml-seg="0"', $result);
        $this->assertStringContainsString($dots, $result);
        $this->assertStringNotContainsString('data-ooxml-seg="1"', $result);
    }

    public function test_has_untranslated_segments_detects_remaining_source_text(): void
    {
        $annotator = new OoxmlHtmlSegmentAnnotator;
        $html = '<p><span data-ooxml-seg="0">[RU] Hello</span> world</p>';

        $this->assertTrue($annotator->hasUntranslatedSegments(
            $html,
            [
                ['id' => 0, 'text' => 'Hello', 'translatable' => true],
                ['id' => 1, 'text' => 'world', 'translatable' => true],
            ],
            [
                0 => '[RU] Hello',
                1 => '[RU] world',
            ],
        ));
    }
}

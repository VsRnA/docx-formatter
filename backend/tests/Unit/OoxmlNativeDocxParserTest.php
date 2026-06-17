<?php

namespace Tests\Unit;

use App\Domain\Docx\ValueObject\BlockType;
use App\Domain\Docx\Service\AnchoredCalloutBlockMerger;
use App\Domain\Docx\Service\ConsecutiveBlocksDeduplicator;
use App\Domain\Docx\Service\FigureGalleryCaptionMerger;
use App\Domain\Docx\Service\DocumentAssembler;
use App\Domain\Docx\Service\ListBlocksGrouper;
use App\Domain\Docx\Service\Support\TextRunFragmentMerger;
use App\Infrastructure\Docx\Ooxml\OoxmlNativeDocxParser;
use App\Infrastructure\Docx\Ooxml\Parsing\Image\OoxmlFigureEligibilityFilter;
use App\Infrastructure\Docx\Ooxml\Parsing\Image\OoxmlFigureHtmlBuilder;
use App\Infrastructure\Docx\Ooxml\Parsing\Image\OoxmlInlineFigureCollector;
use App\Infrastructure\Docx\Ooxml\Parsing\Image\OoxmlPendingFigureQueue;
use App\Infrastructure\Docx\Ooxml\Parsing\Image\OoxmlVmlFigureScanner;
use App\Infrastructure\Docx\Ooxml\Parsing\Layout\ParagraphLayoutHelper;
use App\Infrastructure\Docx\Ooxml\Parsing\Paragraph\ParagraphBlockFactory;
use App\Infrastructure\Docx\Ooxml\Parsing\Paragraph\ParagraphBlockSplitter;
use App\Infrastructure\Docx\Ooxml\Parsing\Table\OoxmlTableCellRenderer;
use App\Infrastructure\Docx\Ooxml\Parsing\Table\OoxmlTableCellSegmentAnnotator;
use App\Infrastructure\Docx\Ooxml\Parsing\Table\OoxmlTableGridBuilder;
use App\Infrastructure\Docx\Ooxml\Parsing\Table\OoxmlTableHtmlBuilder;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlAnchorLayoutParser;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlBodyWalker;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlFallbackBlockFactory;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlDrawingParser;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlHeaderFooterParser;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlImageBlockFactory;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlParagraphParser;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlRunParser;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlSectionPropertiesParser;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlTableParser;
use App\Infrastructure\Docx\Ooxml\Styles\OoxmlNumberingResolver;
use App\Infrastructure\Docx\Ooxml\Styles\OoxmlStyleResolver;
use App\Support\TempFileManager;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class UnitTestTempFileManager extends TempFileManager
{
    public function createPath(string $extension = 'tmp'): string
    {
        return sys_get_temp_dir().'/ooxml-'.uniqid('', true).'.'.$extension;
    }
}

class OoxmlNativeDocxParserTest extends TestCase
{
    private string $fixturePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturePath = $this->createMinimalDocx();
    }

    protected function tearDown(): void
    {
        if (is_file($this->fixturePath)) {
            unlink($this->fixturePath);
        }

        parent::tearDown();
    }

    public function test_parses_paragraph_heading_and_table(): void
    {
        $parser = $this->makeParser();
        $document = $parser->parse($this->fixturePath);

        $this->assertSame('ooxml_native', $document->meta['parser'] ?? null);
        $this->assertGreaterThanOrEqual(3, count($document->blocks));

        $types = array_map(fn ($b) => $b->type, $document->blocks);
        $this->assertContains(BlockType::Heading, $types);
        $this->assertContains(BlockType::Paragraph, $types);
        $this->assertContains(BlockType::Table, $types);

        $heading = $this->firstBlockOfType($document->blocks, BlockType::Heading);
        $this->assertStringContainsString('Test Title', strip_tags((string) $heading?->html));
        $this->assertSame(0, $heading?->meta['ooxml_scope_index'] ?? null);

        $table = $this->firstBlockOfType($document->blocks, BlockType::Table);
        $this->assertStringContainsString('<table', (string) $table?->html);
        $this->assertStringContainsString('Cell A', (string) $table?->html);
    }

    public function test_does_not_duplicate_textbox_segments_from_choice_and_fallback(): void
    {
        $path = $this->createDocxWithDuplicateTextboxChoiceFallback();
        try {
            $document = $this->makeParser()->parse($path);
            $paragraph = $this->firstBlockOfType($document->blocks, BlockType::Paragraph);

            $this->assertNotNull($paragraph);
            $segments = $paragraph->meta['ooxml_segments'] ?? [];
            $this->assertCount(1, $segments);
            $this->assertSame('Locking knob', $segments[0]['text'] ?? null);
            $this->assertSame(1, substr_count((string) $paragraph->textOriginal, 'Locking knob'));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_keeps_figure_gallery_images_in_one_inline_row(): void
    {
        $path = dirname(__DIR__, 2).'/storage/app/mock-cloud/documents/3c99d14c-7372-41cb-a785-201e7638bc2e/source.docx';
        if (! is_file($path)) {
            $this->markTestSkipped('Scarifier fixture DOCX is not available.');
        }

        try {
            $document = $this->makeParser()->parse($path);
            $gallery = null;

            foreach ($document->blocks as $block) {
                if ($block->type !== BlockType::Paragraph) {
                    continue;
                }

                if (! str_contains((string) $block->html, 'doc-figure-gallery')
                    && ! str_contains((string) $block->html, 'doc-figure-canvas')) {
                    continue;
                }

                if (count($block->meta['pending_images'] ?? []) !== 3) {
                    continue;
                }

                $markers = array_map(
                    static fn (array $pending): string => (string) ($pending['relationship_id'] ?? $pending['marker'] ?? ''),
                    $block->meta['pending_images'] ?? [],
                );

                if (! in_array('rId22', $markers, true)) {
                    continue;
                }

                $gallery = $block;

                break;
            }

            $this->assertNotNull($gallery);
            $this->assertSame(3, substr_count((string) $gallery->html, '<figcaption class="doc-figure-caption"'));
            $this->assertStringContainsString('doc-figure-overlay', (string) $gallery->html);
            $this->assertStringContainsString('Fig.2A', (string) $gallery->html);
            $this->assertStringContainsString('Fig.2B', (string) $gallery->html);
            $this->assertStringContainsString('Fig.2C', (string) $gallery->html);
            $this->assertStringNotContainsString('doc-symbol-row', (string) $gallery->html);
            $this->assertTrue(
                str_contains((string) $gallery->html, 'doc-figure-gallery')
                || str_contains((string) $gallery->html, 'doc-figure-canvas'),
            );
            $this->assertCount(3, $gallery->meta['pending_images'] ?? []);
            $this->assertLockingKnobOverlayWithinLastFigure((string) $gallery->html);
        } finally {
            foreach ($document->blocks ?? [] as $block) {
                foreach ($block->meta['pending_images'] ?? [] as $pending) {
                    if (is_string($pending['local_path'] ?? null) && is_file($pending['local_path'])) {
                        unlink($pending['local_path']);
                    }
                }

                if (is_string($block->localImagePath ?? null) && is_file($block->localImagePath)) {
                    unlink($block->localImagePath);
                }
            }
        }
    }

    public function test_merges_consecutive_anchored_callout_blocks(): void
    {
        $path = $this->createDocxWithAnchoredCalloutNumbers();
        try {
            $document = $this->makeParser()->parse($path);
            $paragraphs = array_values(array_filter(
                $document->blocks,
                fn ($block) => $block->type === BlockType::Paragraph,
            ));

            $this->assertCount(1, $paragraphs);
            $html = (string) $paragraphs[0]->html;
            $this->assertStringContainsString('doc-anchored-canvas', $html);
            $this->assertStringContainsString('doc-callout', $html);
            $this->assertSame('3 4 5', $paragraphs[0]->textOriginal);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_renders_anchored_identification_diagram_with_connectors(): void
    {
        $path = dirname(__DIR__, 2).'/storage/app/mock-cloud/documents/9c98356c-c88d-4f3f-ac91-038bfcfec6a9/source.docx';
        if (! is_file($path)) {
            $this->markTestSkipped('Identification diagram fixture is unavailable.');
        }

        $document = $this->makeParser()->parse($path);
        $diagramHtml = null;

        foreach ($document->blocks as $block) {
            $html = (string) ($block->html ?? '');
            if ((! str_contains($html, 'doc-anchored-canvas') && ! str_contains($html, 'doc-figure-canvas'))
                || ! str_contains($html, '<figure')) {
                continue;
            }

            if (str_contains($html, 'doc-anchor-shape')) {
                $diagramHtml = $html;

                break;
            }
        }

        $this->assertNotNull($diagramHtml, 'Expected merged identification diagram block with connector shapes.');
        $this->assertGreaterThanOrEqual(3, substr_count($diagramHtml, 'doc-anchor-shape'), $diagramHtml);
        $this->assertTrue(
            substr_count($diagramHtml, 'doc-callout') >= 3
            || substr_count($diagramHtml, 'doc-figure-overlay') >= 3,
            $diagramHtml,
        );
        $this->assertStringNotContainsString('doc-symbol-row', $diagramHtml);
        $this->assertMatchesRegularExpression('/position:absolute[^>]*top:\d+px[^>]*>[\s\S]*<figure/i', $diagramHtml);
    }

    public function test_does_not_duplicate_repeated_runs(): void
    {
        $path = $this->createDocxWithDuplicateRuns();
        try {
            $document = $this->makeParser()->parse($path);
            $paragraphs = array_values(array_filter(
                $document->blocks,
                fn ($b) => $b->type === BlockType::Paragraph,
            ));

            $this->assertCount(1, $paragraphs);
            $plain = $paragraphs[0]->textOriginal ?? '';
            $this->assertSame(1, substr_count($plain, 'Duplicate Me'));
            $this->assertStringContainsString('<strong>', (string) $paragraphs[0]->html);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_does_not_duplicate_bold_caption(): void
    {
        $caption = 'Рисунок 1. Детальная визуализация архитектуры сети';
        $path = $this->createDocxWithCaptionDuplicate($caption);
        try {
            $document = $this->makeParser()->parse($path);
            $paragraph = $this->firstBlockOfType($document->blocks, BlockType::Paragraph);

            $this->assertSame($caption, $paragraph?->textOriginal);
            $this->assertStringContainsString('<strong>', (string) $paragraph?->html);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_extracts_inline_drawing_image(): void
    {
        $path = $this->createDocxWithInlineImage();
        try {
            $document = $this->makeParser()->parse($path);
            $image = $this->firstBlockOfType($document->blocks, BlockType::Image);

            $this->assertNotNull($image);
            $this->assertNotNull($image->localImagePath);
            $this->assertFileExists($image->localImagePath);
            $this->assertGreaterThan(0, filesize($image->localImagePath));

            if (is_file($image->localImagePath)) {
                unlink($image->localImagePath);
            }
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_extracts_vml_pict_image(): void
    {
        $path = $this->createDocxWithVmlImage();
        try {
            $document = $this->makeParser()->parse($path);
            $image = $this->firstBlockOfType($document->blocks, BlockType::Image);

            $this->assertNotNull($image);
            $this->assertFileExists((string) $image?->localImagePath);

            if ($image?->localImagePath && is_file($image->localImagePath)) {
                unlink($image->localImagePath);
            }
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_keeps_multiple_images_inline_in_one_paragraph(): void
    {
        $path = $this->createDocxWithMultipleInlineImages();
        try {
            $document = $this->makeParser()->parse($path);
            $paragraph = $this->firstBlockOfType($document->blocks, BlockType::Paragraph);

            $this->assertNotNull($paragraph);
            $this->assertStringContainsString('doc-figure-canvas', (string) $paragraph->html);
            $this->assertStringContainsString('doc-paragraph--figure-gallery', (string) $paragraph->html);
            $this->assertStringContainsString('doc-image--inline', (string) $paragraph->html);
            $this->assertStringContainsString('data-pending-marker="rId5"', (string) $paragraph->html);
            $this->assertStringContainsString('data-pending-marker="rId8"', (string) $paragraph->html);

            $pending = $paragraph->meta['pending_images'] ?? [];
            $this->assertCount(2, $pending);
            $this->assertSame('rId5', $pending[0]['relationship_id'] ?? null);
            $this->assertSame('rId8', $pending[1]['relationship_id'] ?? null);

            foreach ($pending as $item) {
                if (is_string($item['local_path'] ?? null) && is_file($item['local_path'])) {
                    unlink($item['local_path']);
                }
            }
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_extracts_image_inside_table_cell(): void
    {
        $path = $this->createDocxWithTableImage();
        try {
            $document = $this->makeParser()->parse($path);
            $table = $this->firstBlockOfType($document->blocks, BlockType::Table);

            $this->assertNotNull($table);
            $this->assertStringContainsString('data-pending-marker="rId7"', (string) $table->html);
            $this->assertStringContainsString('doc-image--inline', (string) $table->html);

            $pending = $table->meta['pending_images'] ?? [];
            $this->assertCount(1, $pending);
            $this->assertSame('rId7', $pending[0]['relationship_id'] ?? null);
            $this->assertFileExists((string) ($pending[0]['local_path'] ?? ''));

            if (is_string($pending[0]['local_path'] ?? null) && is_file($pending[0]['local_path'])) {
                unlink($pending[0]['local_path']);
            }
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_reads_image_dimensions_and_alt_text(): void
    {
        $path = $this->createDocxWithInlineImage();
        try {
            $document = $this->makeParser()->parse($path);
            $image = $this->firstBlockOfType($document->blocks, BlockType::Image);

            $this->assertNotNull($image);
            $this->assertStringContainsString('width="96"', (string) $image->html);
            $this->assertStringContainsString('height="96"', (string) $image->html);
            $this->assertSame('Network diagram', $image->meta['image']['alt'] ?? null);

            if ($image->localImagePath && is_file($image->localImagePath)) {
                unlink($image->localImagePath);
            }
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_extracts_textbox_text_with_inline_image(): void
    {
        $path = $this->createDocxWithTextBoxAndImage();
        try {
            $document = $this->makeParser()->parse($path);
            $paragraph = $this->firstBlockOfType($document->blocks, BlockType::Paragraph);

            $this->assertNotNull($paragraph);
            $this->assertStringContainsString('doc-paragraph--symbols', (string) $paragraph->html);
            $this->assertStringContainsString('doc-symbol-row', (string) $paragraph->html);
            $this->assertStringContainsString('doc-symbol-icons', (string) $paragraph->html);
            $this->assertStringNotContainsString('&amp;quot;', (string) $paragraph->html);
            $this->assertStringContainsString('doc-textbox', (string) $paragraph->html);
            $this->assertStringContainsString('Keep bystanders away', (string) $paragraph->textOriginal);
            $this->assertStringNotContainsString('doc-textbox--anchored', (string) $paragraph->html);
            $this->assertStringNotContainsString('position:absolute', (string) $paragraph->html);
            $this->assertStringContainsString('data-pending-marker="rId5"', (string) $paragraph->html);

            $pending = $paragraph->meta['pending_images'] ?? [];
            $this->assertCount(1, $pending);

            if (is_string($pending[0]['local_path'] ?? null) && is_file($pending[0]['local_path'])) {
                unlink($pending[0]['local_path']);
            }
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_skips_emf_inline_icon_placeholder(): void
    {
        $path = $this->createDocxWithEmfInlineIcon();
        try {
            $document = $this->makeParser()->parse($path);
            $paragraph = $this->firstBlockOfType($document->blocks, BlockType::Paragraph);

            $this->assertNotNull($paragraph);
            $this->assertStringNotContainsString('doc-image--unsupported', (string) $paragraph->html);
            $this->assertStringNotContainsString('data-unsupported-format="emf"', (string) $paragraph->html);
            $this->assertStringContainsString('Before operation', (string) $paragraph->textOriginal);
            $this->assertStringNotContainsString('EMF', (string) $paragraph->textOriginal);

            $pending = $paragraph->meta['pending_images'] ?? [];
            $this->assertSame([], $pending);

            $warnings = $document->meta['warnings'] ?? [];
            $this->assertNotSame([], $warnings);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_parses_section_and_footer_metadata(): void
    {
        $document = $this->makeParser()->parse($this->fixturePath);

        $this->assertSame('ooxml_native', $document->meta['parser'] ?? null);
        $this->assertIsArray($document->meta['section'] ?? null);
        $this->assertArrayHasKey('page_width_mm', $document->meta['section']);
        $this->assertIsArray($document->meta['footers'] ?? null);
    }

    public function test_parses_custom_page_geometry_and_doc_defaults(): void
    {
        $path = $this->createDocxWithCustomPageAndDefaults();
        try {
            $document = $this->makeParser()->parse($path);

            $this->assertSame(215.9, $document->meta['section']['page_width_mm']);
            $this->assertSame(279.4, $document->meta['section']['page_height_mm']);
            $this->assertSame(25.4, $document->meta['section']['margin_left_mm']);
            $this->assertSame('Arial', $document->meta['defaults']['font'] ?? null);
            $this->assertSame(10.0, $document->meta['defaults']['size_pt'] ?? null);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_table_with_fixed_width_preserves_tblw_in_html(): void
    {
        $path = $this->createDocxWithFixedWidthTable();
        try {
            $document = $this->makeParser()->parse($path);
            $table = $this->firstBlockOfType($document->blocks, BlockType::Table);

            $this->assertNotNull($table);
            $this->assertStringContainsString('style="border-collapse:collapse;width:180pt"', (string) $table->html);
            $this->assertStringContainsString('<colgroup>', (string) $table->html);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_splits_page_break_marker_into_separate_blocks(): void
    {
        $path = $this->createDocxWithPageBreakRun();
        try {
            $document = $this->makeParser()->parse($path);
            $paragraphs = array_values(array_filter(
                $document->blocks,
                fn ($b) => $b->type === BlockType::Paragraph,
            ));

            $this->assertGreaterThanOrEqual(2, count($paragraphs));
            $this->assertTrue((bool) ($paragraphs[1]->meta['page_break_before'] ?? false));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_includes_tracked_insertion_text(): void
    {
        $path = $this->createDocxWithTrackedInsertion();
        try {
            $document = $this->makeParser()->parse($path);
            $paragraph = $this->firstBlockOfType($document->blocks, BlockType::Paragraph);

            $this->assertNotNull($paragraph);
            $this->assertStringContainsString('Accepted change', (string) $paragraph->textOriginal);
            $this->assertStringNotContainsString('Rejected change', (string) $paragraph->textOriginal);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_parses_math_formula_without_translation_text(): void
    {
        $path = $this->createDocxWithMathFormula();
        try {
            $document = $this->makeParser()->parse($path);
            $formula = $this->firstBlockOfType($document->blocks, BlockType::Formula);

            $this->assertNotNull($formula);
            $this->assertNull($formula->textOriginal);
            $this->assertStringContainsString('data-doc-formula="1"', (string) $formula->html);
            $this->assertStringContainsString('E=mc2', (string) $formula->html);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_parses_symbol_run(): void
    {
        $path = $this->createDocxWithSymbolRun();
        try {
            $document = $this->makeParser()->parse($path);
            $paragraph = $this->firstBlockOfType($document->blocks, BlockType::Paragraph);

            $this->assertNotNull($paragraph);
            $this->assertStringContainsString('dash item', (string) $paragraph->textOriginal);
            $this->assertStringContainsString("\u{2011}", (string) $paragraph->textOriginal);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_keeps_legitimate_repeated_words_in_paragraph(): void
    {
        $path = $this->createDocxWithRepeatedWordsInParagraph();
        try {
            $document = $this->makeParser()->parse($path);
            $paragraph = $this->firstBlockOfType($document->blocks, BlockType::Paragraph);

            $this->assertNotNull($paragraph);
            $this->assertSame('Repeat me Repeat me', $paragraph->textOriginal);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_layout_table_renders_without_imposed_borders(): void
    {
        $path = $this->createDocxWithLayoutTable();
        try {
            $document = $this->makeParser()->parse($path);
            $table = $this->firstBlockOfType($document->blocks, BlockType::Table);

            $this->assertNotNull($table);
            $this->assertStringContainsString('doc-table--layout', (string) $table->html);
            $this->assertStringContainsString('<colgroup>', (string) $table->html);
            $this->assertStringContainsString('width:', (string) $table->html);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_bordered_table_keeps_data_table_class(): void
    {
        $path = $this->createDocxWithBorderedTable();
        try {
            $document = $this->makeParser()->parse($path);
            $table = $this->firstBlockOfType($document->blocks, BlockType::Table);

            $this->assertNotNull($table);
            $this->assertStringNotContainsString('doc-table--layout', (string) $table->html);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_resolves_character_style_color(): void
    {
        $path = $this->createDocxWithCharacterStyle();
        try {
            $document = $this->makeParser()->parse($path);
            $paragraph = $this->firstBlockOfType($document->blocks, BlockType::Paragraph);

            $this->assertNotNull($paragraph);
            $this->assertStringContainsString('Styled text', (string) $paragraph->textOriginal);
            $this->assertMatchesRegularExpression('/color:#ff0000/i', (string) $paragraph->html);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function createDocxWithLayoutTable(): string
    {
        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:tbl>
      <w:tblPr><w:tblW w:type="dxa" w:w="4800"/></w:tblPr>
      <w:tblGrid>
        <w:gridCol w:w="3600"/>
        <w:gridCol w:w="1200"/>
      </w:tblGrid>
      <w:tr>
        <w:tc><w:p><w:r><w:t>Left</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>Right</w:t></w:r></w:p></w:tc>
      </w:tr>
    </w:tbl>
    <w:sectPr/>
  </w:body>
</w:document>
XML;

        return $this->writeDocx([
            'word/document.xml' => $documentXml,
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>',
        ]);
    }

    private function createDocxWithBorderedTable(): string
    {
        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:tbl>
      <w:tblPr>
        <w:tblBorders>
          <w:top w:val="single" w:sz="4" w:color="000000"/>
          <w:left w:val="single" w:sz="4" w:color="000000"/>
          <w:bottom w:val="single" w:sz="4" w:color="000000"/>
          <w:right w:val="single" w:sz="4" w:color="000000"/>
        </w:tblBorders>
      </w:tblPr>
      <w:tr>
        <w:tc><w:p><w:r><w:t>Header</w:t></w:r></w:p></w:tc>
      </w:tr>
    </w:tbl>
    <w:sectPr/>
  </w:body>
</w:document>
XML;

        return $this->writeDocx([
            'word/document.xml' => $documentXml,
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>',
        ]);
    }

    private function createDocxWithCharacterStyle(): string
    {
        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r>
        <w:rPr><w:rStyle w:val="Accent15"/></w:rPr>
        <w:t>Styled text</w:t>
      </w:r>
    </w:p>
    <w:sectPr/>
  </w:body>
</w:document>
XML;

        $stylesXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="character" w:customStyle="1" w:styleId="Accent15">
    <w:name w:val="Accent 15"/>
    <w:rPr><w:color w:val="FF0000"/></w:rPr>
  </w:style>
</w:styles>
XML;

        return $this->writeDocx([
            'word/document.xml' => $documentXml,
            'word/styles.xml' => $stylesXml,
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>',
        ]);
    }

    public function test_keeps_doubled_number_intact(): void
    {
        $path = $this->createDocxWithSingleRun('20242024');
        try {
            $document = $this->makeParser()->parse($path);
            $paragraph = $this->firstBlockOfType($document->blocks, BlockType::Paragraph);

            $this->assertNotNull($paragraph);
            $this->assertSame('20242024', $paragraph->textOriginal);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_keeps_short_doubled_token_intact(): void
    {
        $path = $this->createDocxWithSingleRun('okok');
        try {
            $document = $this->makeParser()->parse($path);
            $paragraph = $this->firstBlockOfType($document->blocks, BlockType::Paragraph);

            $this->assertNotNull($paragraph);
            $this->assertSame('okok', $paragraph->textOriginal);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function createDocxWithSingleRun(string $text): string
    {
        $documentXml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t>{$text}</w:t></w:r>
    </w:p>
    <w:sectPr/>
  </w:body>
</w:document>
XML;

        return $this->writeDocx([
            'word/document.xml' => $documentXml,
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>',
        ]);
    }

    private function createDocxWithTrackedInsertion(): string
    {
        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t>Before </w:t></w:r>
      <w:ins>
        <w:r><w:t>Accepted change</w:t></w:r>
      </w:ins>
      <w:del>
        <w:r><w:delText>Rejected change</w:delText></w:r>
      </w:del>
    </w:p>
    <w:sectPr/>
  </w:body>
</w:document>
XML;

        return $this->writeDocx([
            'word/document.xml' => $documentXml,
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>',
        ]);
    }

    private function createDocxWithMathFormula(): string
    {
        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document
  xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math">
  <w:body>
    <m:oMathPara>
      <m:oMath>
        <m:r><m:t>E=mc2</m:t></m:r>
      </m:oMath>
    </m:oMathPara>
    <w:sectPr/>
  </w:body>
</w:document>
XML;

        return $this->writeDocx([
            'word/document.xml' => $documentXml,
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>',
        ]);
    }

    private function createDocxWithSymbolRun(): string
    {
        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:noBreakHyphen/></w:r>
      <w:r><w:t>dash item</w:t></w:r>
    </w:p>
    <w:sectPr/>
  </w:body>
</w:document>
XML;

        return $this->writeDocx([
            'word/document.xml' => $documentXml,
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>',
        ]);
    }

    private function createDocxWithRepeatedWordsInParagraph(): string
    {
        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t>Repeat me </w:t></w:r>
      <w:r><w:t>Repeat me</w:t></w:r>
    </w:p>
    <w:sectPr/>
  </w:body>
</w:document>
XML;

        return $this->writeDocx([
            'word/document.xml' => $documentXml,
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>',
        ]);
    }

    private function makeParser(): OoxmlNativeDocxParser
    {
        $styles = new OoxmlStyleResolver;
        $numbering = new OoxmlNumberingResolver;
        $tempFiles = new UnitTestTempFileManager;
        $anchors = new OoxmlAnchorLayoutParser;
        $drawings = new OoxmlDrawingParser($tempFiles, $anchors);
        $figureHtml = new OoxmlFigureHtmlBuilder;
        $figureEligibility = new OoxmlFigureEligibilityFilter($drawings);
        $figureQueue = new OoxmlPendingFigureQueue($drawings, $figureHtml, $figureEligibility);
        $vmlScanner = new OoxmlVmlFigureScanner($figureQueue);
        $inlineFigures = new OoxmlInlineFigureCollector($drawings, $figureEligibility, $figureQueue, $vmlScanner);
        $images = new OoxmlImageBlockFactory($drawings, $figureHtml, $inlineFigures);
        $merger = new TextRunFragmentMerger;
        $textFormatter = new \App\Infrastructure\Docx\Ooxml\Parsing\Run\OoxmlRunTextFormatter($styles);
        $symbolRows = new \App\Infrastructure\Docx\Ooxml\Parsing\Layout\SymbolRowLayout;
        $layout = new ParagraphLayoutHelper;
        $textBoxes = new \App\Infrastructure\Docx\Ooxml\Parsing\Run\OoxmlTextBoxRenderer($anchors);
        $shapes = new \App\Infrastructure\Docx\Ooxml\Parsing\Run\OoxmlAnchorShapeRenderer($anchors);
        $alternateContent = new \App\Infrastructure\Docx\Ooxml\Parsing\Run\OoxmlAlternateContentRenderer($images, $textBoxes, $shapes, $symbolRows);
        $math = new \App\Infrastructure\Docx\Ooxml\Parsing\Run\OoxmlMathRenderer;
        $runs = new OoxmlRunParser($merger, $textFormatter, $alternateContent, $symbolRows, $math);
        $textNodeIndex = new \App\Infrastructure\Docx\Ooxml\Writing\OoxmlTextNodeIndex;
        $segmentCollector = new \App\Infrastructure\Docx\Ooxml\Writing\OoxmlTextSegmentCollector($textNodeIndex);
        $segmentHtml = new \App\Infrastructure\Docx\Ooxml\Parsing\OoxmlHtmlSegmentAnnotator;
        $blockFactory = new ParagraphBlockFactory($styles, $numbering, $segmentCollector, $segmentHtml, $layout);
        $blockSplitter = new ParagraphBlockSplitter($layout, $blockFactory);
        $tableCells = new OoxmlTableCellRenderer($runs);
        $tableGrid = new OoxmlTableGridBuilder($tableCells);
        $tableCellSegments = new OoxmlTableCellSegmentAnnotator($segmentCollector, $segmentHtml);
        $tableHtmlBuilder = new OoxmlTableHtmlBuilder($tableGrid, $tableCellSegments);
        $walker = new OoxmlBodyWalker(
            new OoxmlParagraphParser($styles, $numbering, $runs, $images, $anchors, $blockSplitter, $layout),
            new OoxmlTableParser($tableHtmlBuilder),
            new OoxmlFallbackBlockFactory,
            $math,
        );
        $supplementary = new \App\Infrastructure\Docx\Ooxml\Parsing\OoxmlSupplementaryBlocksParser($walker);
        $merger = new TextRunFragmentMerger;
        $assembler = new DocumentAssembler(
            new ListBlocksGrouper,
            new ConsecutiveBlocksDeduplicator($merger),
            new AnchoredCalloutBlockMerger,
            new FigureGalleryCaptionMerger,
        );

        return new OoxmlNativeDocxParser(
            $walker,
            $styles,
            $numbering,
            $assembler,
            new OoxmlSectionPropertiesParser,
            new OoxmlHeaderFooterParser($runs),
            $supplementary,
            $anchors,
        );
    }

    private function createDocxWithPageBreakRun(): string
    {
        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t>Before break</w:t></w:r>
      <w:r><w:br w:type="page"/></w:r>
      <w:r><w:t>After break</w:t></w:r>
    </w:p>
    <w:sectPr/>
  </w:body>
</w:document>
XML;

        return $this->writeDocx([
            'word/document.xml' => $documentXml,
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>',
        ]);
    }

    private function createDocxWithCustomPageAndDefaults(): string
    {
        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t>Custom page body.</w:t></w:r>
    </w:p>
    <w:sectPr>
      <w:pgSz w:w="12240" w:h="15840"/>
      <w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/>
    </w:sectPr>
  </w:body>
</w:document>
XML;

        $stylesXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:docDefaults>
    <w:rPrDefault>
      <w:rPr>
        <w:rFonts w:ascii="Arial" w:hAnsi="Arial"/>
        <w:sz w:val="20"/>
      </w:rPr>
    </w:rPrDefault>
  </w:docDefaults>
</w:styles>
XML;

        return $this->writeDocx([
            'word/document.xml' => $documentXml,
            'word/styles.xml' => $stylesXml,
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>',
        ]);
    }

    private function createDocxWithFixedWidthTable(): string
    {
        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:tbl>
      <w:tblPr>
        <w:tblW w:w="3600" w:type="dxa"/>
        <w:tblBorders>
          <w:top w:val="single" w:sz="4" w:space="0" w:color="auto"/>
          <w:left w:val="single" w:sz="4" w:space="0" w:color="auto"/>
          <w:bottom w:val="single" w:sz="4" w:space="0" w:color="auto"/>
          <w:right w:val="single" w:sz="4" w:space="0" w:color="auto"/>
        </w:tblBorders>
      </w:tblPr>
      <w:tblGrid>
        <w:gridCol w:w="1800"/>
        <w:gridCol w:w="1800"/>
      </w:tblGrid>
      <w:tr>
        <w:tc><w:p><w:r><w:t>Left</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>Right</w:t></w:r></w:p></w:tc>
      </w:tr>
    </w:tbl>
    <w:sectPr/>
  </w:body>
</w:document>
XML;

        return $this->writeDocx([
            'word/document.xml' => $documentXml,
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>',
        ]);
    }

    private function createMinimalDocx(): string
    {
        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:pPr><w:pStyle w:val="Heading1"/></w:pPr>
      <w:r><w:rPr><w:b/></w:rPr><w:t>Test Title</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t>Body paragraph text.</w:t></w:r>
    </w:p>
    <w:tbl>
      <w:tr>
        <w:tc><w:p><w:r><w:t>Cell A</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>Cell B</w:t></w:r></w:p></w:tc>
      </w:tr>
    </w:tbl>
    <w:sectPr/>
  </w:body>
</w:document>
XML;

        $stylesXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="Heading1">
    <w:name w:val="heading 1"/>
    <w:rPr><w:b/></w:rPr>
  </w:style>
</w:styles>
XML;

        return $this->writeDocx([
            'word/document.xml' => $documentXml,
            'word/styles.xml' => $stylesXml,
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>',
        ]);
    }

    private function createDocxWithCaptionDuplicate(string $caption): string
    {
        $documentXml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t>{$caption}</w:t></w:r>
      <w:r><w:rPr><w:b/></w:rPr><w:t>{$caption}</w:t></w:r>
    </w:p>
    <w:sectPr/>
  </w:body>
</w:document>
XML;

        return $this->writeDocx([
            'word/document.xml' => $documentXml,
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>',
        ]);
    }

    private function createDocxWithInlineImage(): string
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document
  xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
  xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">
  <w:body>
    <w:p>
      <w:r>
        <w:drawing>
          <wp:inline>
            <wp:extent cx="914400" cy="914400"/>
            <wp:docPr descr="Network diagram"/>
            <a:graphic>
              <a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">
                <pic:pic>
                  <pic:blipFill>
                    <a:blip r:embed="rId5"/>
                  </pic:blipFill>
                </pic:pic>
              </a:graphicData>
            </a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
    </w:p>
    <w:sectPr/>
  </w:body>
</w:document>
XML;

        return $this->writeDocxWithBinary([
            'word/document.xml' => $documentXml,
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image1.png"/>
</Relationships>',
            'word/media/image1.png' => $png,
        ]);
    }

    private function createDocxWithVmlImage(): string
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document
  xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
  xmlns:v="urn:schemas-microsoft-com:vml">
  <w:body>
    <w:p>
      <w:r>
        <w:pict>
          <v:shape>
            <v:imagedata r:id="rId6"/>
          </v:shape>
        </w:pict>
      </w:r>
    </w:p>
    <w:sectPr/>
  </w:body>
</w:document>
XML;

        return $this->writeDocxWithBinary([
            'word/document.xml' => $documentXml,
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId6" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image2.png"/>
</Relationships>',
            'word/media/image2.png' => $png,
        ]);
    }

    private function createDocxWithMultipleInlineImages(): string
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document
  xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
  xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">
  <w:body>
    <w:p>
      <w:r>
        <w:drawing>
          <wp:inline>
            <a:graphic>
              <a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">
                <pic:pic>
                  <pic:blipFill>
                    <a:blip r:embed="rId5"/>
                  </pic:blipFill>
                </pic:pic>
              </a:graphicData>
            </a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
      <w:r>
        <w:drawing>
          <wp:inline>
            <a:graphic>
              <a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">
                <pic:pic>
                  <pic:blipFill>
                    <a:blip r:embed="rId8"/>
                  </pic:blipFill>
                </pic:pic>
              </a:graphicData>
            </a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
    </w:p>
    <w:sectPr/>
  </w:body>
</w:document>
XML;

        return $this->writeDocxWithBinary([
            'word/document.xml' => $documentXml,
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image1.png"/>
  <Relationship Id="rId8" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image2.png"/>
</Relationships>',
            'word/media/image1.png' => $png,
            'word/media/image2.png' => $png,
        ]);
    }

    private function createDocxWithTableImage(): string
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document
  xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
  xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">
  <w:body>
    <w:tbl>
      <w:tr>
        <w:tc>
          <w:p>
            <w:r>
              <w:drawing>
                <wp:inline>
                  <a:graphic>
                    <a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">
                      <pic:pic>
                        <pic:blipFill>
                          <a:blip r:embed="rId7"/>
                        </pic:blipFill>
                      </pic:pic>
                    </a:graphicData>
                  </a:graphic>
                </wp:inline>
              </w:drawing>
            </w:r>
          </w:p>
        </w:tc>
      </w:tr>
    </w:tbl>
    <w:sectPr/>
  </w:body>
</w:document>
XML;

        return $this->writeDocxWithBinary([
            'word/document.xml' => $documentXml,
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId7" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image3.png"/>
</Relationships>',
            'word/media/image3.png' => $png,
        ]);
    }

    private function createDocxWithTextBoxAndImage(): string
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document
  xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
  xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"
  xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:wps="http://schemas.microsoft.com/office/word/2010/wordprocessingShape"
  xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"
  xmlns:v="urn:schemas-microsoft-com:vml">
  <w:body>
    <w:p>
      <w:r>
        <mc:AlternateContent>
          <mc:Choice Requires="wps">
            <w:drawing>
              <wp:anchor distT="0" distB="0" distL="0" distR="0">
                <wp:extent cx="4572000" cy="495300"/>
                <a:graphic>
                  <a:graphicData uri="http://schemas.microsoft.com/office/word/2010/wordprocessingShape">
                    <wps:wsp>
                      <wps:spPr/>
                      <wps:txbx>
                        <w:txbxContent>
                          <w:p><w:r><w:t>Keep bystanders away.</w:t></w:r></w:p>
                        </w:txbxContent>
                      </wps:txbx>
                    </wps:wsp>
                  </a:graphicData>
                </a:graphic>
              </wp:anchor>
            </w:drawing>
          </mc:Choice>
          <mc:Fallback>
            <w:pict>
              <v:shape>
                <v:imagedata r:id="rId5"/>
              </v:shape>
            </w:pict>
          </mc:Fallback>
        </mc:AlternateContent>
      </w:r>
    </w:p>
    <w:sectPr/>
  </w:body>
</w:document>
XML;

        return $this->writeDocxWithBinary([
            'word/document.xml' => $documentXml,
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image1.png"/>
</Relationships>',
            'word/media/image1.png' => $png,
        ]);
    }

    private function createDocxWithEmfInlineIcon(): string
    {
        $emf = hex2bin('0100000000000000000000000000000000000000000000000000000000000000');
        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document
  xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
  xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">
  <w:body>
    <w:p>
      <w:r>
        <w:drawing>
          <wp:inline>
            <wp:extent cx="314325" cy="285750"/>
            <a:graphic>
              <a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">
                <pic:pic>
                  <pic:blipFill>
                    <a:blip r:embed="rId9"/>
                  </pic:blipFill>
                </pic:pic>
              </a:graphicData>
            </a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
      <w:r><w:rPr><w:b/></w:rPr><w:t>Before operation read the manual.</w:t></w:r>
    </w:p>
    <w:sectPr/>
  </w:body>
</w:document>
XML;

        return $this->writeDocxWithBinary([
            'word/document.xml' => $documentXml,
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId9" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image4.emf"/>
</Relationships>',
            'word/media/image4.emf' => $emf,
        ]);
    }

    private function createDocxWithDuplicateTextboxChoiceFallback(): string
    {
        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document
  xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"
  xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:wps="http://schemas.microsoft.com/office/word/2010/wordprocessingShape"
  xmlns:v="urn:schemas-microsoft-com:vml">
  <w:body>
    <w:p>
      <w:r>
        <mc:AlternateContent>
          <mc:Choice Requires="wps">
            <w:drawing>
              <wp:anchor distT="0" distB="0" distL="0" distR="0">
                <wp:extent cx="2000000" cy="400000"/>
                <wp:positionH relativeFrom="column"><wp:posOffset>100000</wp:posOffset></wp:positionH>
                <wp:positionV relativeFrom="paragraph"><wp:posOffset>100000</wp:posOffset></wp:positionV>
                <a:graphic>
                  <a:graphicData uri="http://schemas.microsoft.com/office/word/2010/wordprocessingShape">
                    <wps:wsp>
                      <wps:spPr/>
                      <wps:txbx>
                        <w:txbxContent>
                          <w:p><w:r><w:t>Locking knob</w:t></w:r></w:p>
                        </w:txbxContent>
                      </wps:txbx>
                    </wps:wsp>
                  </a:graphicData>
                </a:graphic>
              </wp:anchor>
            </w:drawing>
          </mc:Choice>
          <mc:Fallback>
            <w:pict>
              <v:shape>
                <v:textbox>
                  <w:txbxContent>
                    <w:p><w:r><w:t>Locking knob</w:t></w:r></w:p>
                  </w:txbxContent>
                </v:textbox>
              </v:shape>
            </w:pict>
          </mc:Fallback>
        </mc:AlternateContent>
      </w:r>
    </w:p>
    <w:sectPr/>
  </w:body>
</w:document>
XML;

        return $this->writeDocx([
            'word/document.xml' => $documentXml,
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>',
        ]);
    }

    private function createDocxWithAnchoredCalloutNumbers(): string
    {
        $calloutParagraph = static function (string $number, int $left, int $top): string {
            return <<<XML
    <w:p>
      <w:r>
        <w:drawing>
          <wp:anchor distT="0" distB="0" distL="0" distR="0">
            <wp:extent cx="400000" cy="300000"/>
            <wp:positionH relativeFrom="column"><wp:posOffset>{$left}</wp:posOffset></wp:positionH>
            <wp:positionV relativeFrom="paragraph"><wp:posOffset>{$top}</wp:posOffset></wp:positionV>
            <a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
              <a:graphicData uri="http://schemas.microsoft.com/office/word/2010/wordprocessingShape">
                <wps:wsp xmlns:wps="http://schemas.microsoft.com/office/word/2010/wordprocessingShape">
                  <wps:spPr/>
                  <wps:txbx>
                    <w:txbxContent>
                      <w:p><w:r><w:t>{$number}</w:t></w:r></w:p>
                    </w:txbxContent>
                  </wps:txbx>
                </wps:wsp>
              </a:graphicData>
            </a:graphic>
          </wp:anchor>
        </w:drawing>
      </w:r>
    </w:p>
XML;
        };

        $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
            .' xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing">'
            .'<w:body>'
            .$calloutParagraph('3', 100000, 100000)
            .$calloutParagraph('4', 200000, 150000)
            .$calloutParagraph('5', 300000, 200000)
            .'<w:sectPr/></w:body></w:document>';

        return $this->writeDocx([
            'word/document.xml' => $documentXml,
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>',
        ]);
    }

    private function createDocxWithDuplicateRuns(): string
    {
        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t>Duplicate Me</w:t></w:r>
      <w:r><w:rPr><w:b/></w:rPr><w:t>Duplicate Me</w:t></w:r>
    </w:p>
    <w:sectPr/>
  </w:body>
</w:document>
XML;

        return $this->writeDocx([
            'word/document.xml' => $documentXml,
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>',
        ]);
    }

    private function assertLockingKnobOverlayWithinLastFigure(string $html): void
    {
        if (! preg_match('/Locking knob/', $html)) {
            $this->fail('Expected Locking knob overlay in gallery HTML.');
        }

        if (str_contains($html, 'doc-figure-canvas')) {
            $this->assertSame(
                1,
                preg_match(
                    '/(<div class="doc-figure-canvas"[\s\S]*?<\/div>\s*<\/div>)/',
                    $html,
                    $galleryMatch,
                ),
                'Expected coordinate figure canvas block.',
            );

            $galleryHtml = $galleryMatch[1];

            preg_match_all(
                '/style="position:absolute;left:(\d+)px;top:(\d+)px;margin:0;z-index:0"[^>]*width="(\d+)"/',
                $galleryHtml,
                $figures,
                PREG_SET_ORDER,
            );
            preg_match_all(
                '/<div class="doc-figure-overlay" style="([^"]*)"[^>]*>([\s\S]*?)<\/div>/',
                $galleryHtml,
                $overlays,
                PREG_SET_ORDER,
            );

            $lockingOverlay = null;
            foreach ($overlays as $overlay) {
                if (str_contains(strip_tags($overlay[2]), 'Locking knob')) {
                    $lockingOverlay = $overlay;
                }
            }

            $this->assertNotEmpty($figures, 'Expected canvas figures.');
            $this->assertNotNull($lockingOverlay, 'Expected Locking knob overlay in coordinate canvas.');

            $lastFigure = $figures[array_key_last($figures)];
            $lastLeft = (int) $lastFigure[1];
            $lastWidth = (int) $lastFigure[3];
            $this->assertSame(1, preg_match('/left:(\d+)px/', $lockingOverlay[1], $leftMatch));
            $overlayLeft = (int) $leftMatch[1];

            $this->assertGreaterThanOrEqual($lastLeft, $overlayLeft);
            $this->assertLessThanOrEqual($lastLeft + $lastWidth, $overlayLeft);

            return;
        }

        if (str_contains($html, 'doc-figure-gallery--positioned')) {
            $this->assertSame(
                1,
                preg_match(
                    '/(<div class="doc-figure-gallery doc-figure-gallery--positioned"[\s\S]*?<\/div>\s*<div class="doc-figure-gallery__captions"[\s\S]*?<\/div>\s*<\/div>)/',
                    $html,
                    $galleryMatch,
                ),
                'Expected positioned figure gallery block.',
            );

            $galleryHtml = $galleryMatch[1];

            preg_match_all(
                '/style="position:absolute;left:(\d+)px;top:\d+px;margin:0;z-index:0"[^>]*width="(\d+)"/',
                $galleryHtml,
                $figures,
                PREG_SET_ORDER,
            );
            preg_match_all(
                '/<div class="doc-figure-overlay" style="([^"]*)"[^>]*>([\s\S]*?)<\/div>/',
                $galleryHtml,
                $overlays,
                PREG_SET_ORDER,
            );

            $lockingOverlay = null;
            foreach ($overlays as $overlay) {
                if (str_contains(strip_tags($overlay[2]), 'Locking knob')) {
                    $lockingOverlay = $overlay;
                }
            }

            $this->assertNotEmpty($figures, 'Expected positioned gallery figures.');
            $this->assertNotNull($lockingOverlay, 'Expected Locking knob overlay in positioned gallery.');

            $lastFigure = $figures[array_key_last($figures)];
            $lastLeft = (int) $lastFigure[1];
            $lastWidth = (int) $lastFigure[2];
            $this->assertSame(1, preg_match('/left:(\d+)px/', $lockingOverlay[1], $leftMatch));
            $overlayLeft = (int) $leftMatch[1];

            $this->assertGreaterThanOrEqual(
                $lastLeft,
                $overlayLeft,
                'Locking knob overlay should not start before the last figure.',
            );
            $this->assertLessThanOrEqual(
                $lastLeft + $lastWidth,
                $overlayLeft,
                'Locking knob overlay left should not exceed the right edge of the last figure.',
            );

            return;
        }

        preg_match_all(
            '/<figure class="doc-figure-cell"[^>]*>.*?<figure class="doc-image[^"]*"[^>]*width="(\d+)"[^>]*>.*?<\/figure>.*?<div class="doc-figure-overlay" style="([^"]*)">([\s\S]*?)<\/div>/s',
            $html,
            $cells,
            PREG_SET_ORDER,
        );

        $this->assertNotEmpty($cells, 'Expected figure cells with overlays in gallery HTML.');

        $lockingCell = null;
        foreach ($cells as $cell) {
            if (str_contains(strip_tags($cell[3]), 'Locking knob')) {
                $lockingCell = $cell;
            }
        }

        $this->assertNotNull($lockingCell, 'Expected Locking knob overlay in gallery cells.');

        $figureWidth = (int) $lockingCell[1];
        $overlayStyle = $lockingCell[2];

        $this->assertSame(1, preg_match('/left:(\d+)px/', $overlayStyle, $leftMatch));
        $overlayLeft = (int) $leftMatch[1];

        $this->assertGreaterThanOrEqual(0, $overlayLeft, 'Locking knob overlay should stay within its figure cell.');
        $this->assertLessThanOrEqual(
            $figureWidth,
            $overlayLeft,
            'Locking knob overlay left should not exceed the right edge of its figure cell.',
        );
    }

    /**
     * @param  list<\App\Domain\Docx\Entity\ParsedBlock>  $blocks
     */
    private function firstBlockOfType(array $blocks, BlockType $type): ?\App\Domain\Docx\Entity\ParsedBlock
    {
        foreach ($blocks as $block) {
            if ($block->type === $type) {
                return $block;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function writeDocx(array $entries): string
    {
        return $this->writeDocxWithBinary($entries);
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function writeDocxWithBinary(array $entries): string
    {
        $path = sys_get_temp_dir().'/ooxml-test-'.uniqid('', true).'.docx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return $path;
    }
}

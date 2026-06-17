<?php

namespace Tests\Unit;

use App\Domain\Document\Entity\DocumentBlock;
use App\Domain\Document\ValueObject\TranslationStatus;
use App\Domain\Docx\ValueObject\BlockType;
use App\Infrastructure\Docx\Ooxml\Ir\HtmlRenderer;
use App\Infrastructure\Docx\Ooxml\Ir\Renderers\FallbackIrRenderer;
use App\Infrastructure\Docx\Ooxml\Ir\Renderers\TextBlockIrRenderer;
use PHPUnit\Framework\TestCase;

final class HtmlRendererTest extends TestCase
{
    private HtmlRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new HtmlRenderer(new TextBlockIrRenderer, new FallbackIrRenderer);
    }

    public function test_renders_ooxml_fallback_from_ir(): void
    {
        $block = $this->makeBlock([
            'type' => BlockType::HtmlRaw,
            'textOriginal' => 'Fallback text',
            'contentJson' => ['kind' => 'ooxml_fallback', 'localName' => 'customXml'],
            'html' => '<div>old</div>',
        ]);

        $html = $this->renderer->renderBlock($block);

        $this->assertStringContainsString('doc-raw-ooxml', (string) $html);
        $this->assertStringContainsString('Fallback text', (string) $html);
        $this->assertStringContainsString('data-ooxml-tag="customXml"', (string) $html);
    }

    public function test_renders_text_block_from_ir_with_translation(): void
    {
        $block = $this->makeBlock([
            'type' => BlockType::Paragraph,
            'textTranslated' => 'Перевод',
            'contentJson' => [
                'kind' => 'paragraph',
                'children' => [['kind' => 'text', 'segmentId' => 1, 'text' => 'Original']],
            ],
            'meta' => [
                'ai_normalized' => true,
                'ooxml_segment_translations' => [1 => 'Перевод'],
            ],
            'html' => '<p>old</p>',
        ]);

        $html = $this->renderer->renderBlock($block);

        $this->assertStringContainsString('data-ooxml-seg="1"', (string) $html);
        $this->assertStringContainsString('Перевод', (string) $html);
    }

    public function test_falls_back_for_tables_and_edited_blocks(): void
    {
        $table = $this->makeBlock([
            'type' => BlockType::Table,
            'contentJson' => ['kind' => 'table', 'rows' => 1, 'cols' => 1],
            'html' => '<table class="doc-table"><tr><td>cell</td></tr></table>',
        ]);
        $edited = $this->makeBlock([
            'type' => BlockType::Paragraph,
            'contentJson' => ['kind' => 'paragraph', 'children' => [['kind' => 'text', 'text' => 'x']]],
            'meta' => ['content_edited' => true],
            'html' => '<p>edited</p>',
        ]);
        $parsed = $this->makeBlock([
            'type' => BlockType::Paragraph,
            'contentJson' => ['kind' => 'paragraph', 'children' => [['kind' => 'text', 'text' => 'plain']]],
            'meta' => ['parse' => true],
            'html' => '<p><strong>bold</strong></p>',
        ]);

        $this->assertNull($this->renderer->renderBlock($table));
        $this->assertNull($this->renderer->renderBlock($edited));
        $this->assertNull($this->renderer->renderBlock($parsed));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeBlock(array $attributes): DocumentBlock
    {
        return new DocumentBlock(
            id: '00000000-0000-0000-0000-000000000001',
            type: $attributes['type'] ?? BlockType::Paragraph,
            sort: 0,
            html: $attributes['html'] ?? null,
            textOriginal: $attributes['textOriginal'] ?? null,
            textTranslated: $attributes['textTranslated'] ?? null,
            translationStatus: TranslationStatus::Skipped,
            styles: $attributes['styles'] ?? null,
            meta: $attributes['meta'] ?? [],
            assets: $attributes['assets'] ?? null,
            contentJson: $attributes['contentJson'] ?? null,
        );
    }
}

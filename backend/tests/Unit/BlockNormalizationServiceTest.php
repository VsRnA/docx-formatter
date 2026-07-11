<?php

namespace Tests\Unit;

use App\Enums\BlockType;
use App\Infrastructure\Document\Normalize\BlockNormalizationService;
use App\Infrastructure\Docx\Ooxml\Ir\HtmlRenderer;
use App\Infrastructure\Docx\Ooxml\Ir\Renderers\FallbackIrRenderer;
use App\Infrastructure\Docx\Ooxml\Ir\Renderers\TextBlockIrRenderer;
use App\Infrastructure\External\Ai\MockBlockNormalizerService;
use App\Models\DocumentBlock;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class BlockNormalizationServiceTest extends TestCase
{
    public function test_normalize_block_updates_type_and_html(): void
    {
        $block = new DocumentBlock;
        $block->forceFill([
            'id' => 'block-1',
            'sort' => 0,
            'type' => BlockType::HtmlRaw,
            'translation_status' => 'skipped',
            'text_original' => 'Warning label',
            'html' => '<div class="doc-raw-ooxml">Warning label</div>',
            'content_json' => ['kind' => 'ooxml_fallback', 'localName' => 'customXml'],
            'meta_json' => [
                'needs_review' => true,
                'ooxml_fragment' => '<w:customXml>Warning label</w:customXml>',
                'confidence' => 0,
            ],
        ]);

        $service = new BlockNormalizationService(
            new MockBlockNormalizerService,
            new HtmlRenderer(new TextBlockIrRenderer, new FallbackIrRenderer),
        );

        $method = new ReflectionMethod(BlockNormalizationService::class, 'normalizeBlock');
        $result = $method->invoke($service, $block);

        $this->assertTrue($result);
        $this->assertSame(BlockType::Paragraph, $block->type);
        $this->assertTrue($block->meta_json['ai_normalized'] ?? false);
        $this->assertFalse($block->meta_json['needs_review'] ?? true);
        $this->assertStringContainsString('Warning label', (string) $block->html);
    }
}

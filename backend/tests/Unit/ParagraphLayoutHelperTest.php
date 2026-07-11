<?php

namespace Tests\Unit;

use App\Infrastructure\Docx\Ooxml\Parsing\Layout\ParagraphLayoutHelper;
use Tests\TestCase;

class ParagraphLayoutHelperTest extends TestCase
{
    private ParagraphLayoutHelper $layout;

    protected function setUp(): void
    {
        parent::setUp();
        $this->layout = new ParagraphLayoutHelper;
    }

    public function test_wraps_multiple_simple_images_in_grid(): void
    {
        $figureA = '<figure class="doc-image doc-image--inline" data-pending-marker="rId1"><img data-pending="1" alt="A" /></figure>';
        $figureB = '<figure class="doc-image doc-image--inline" data-pending-marker="rId2"><img data-pending="1" alt="B" /></figure>';
        $pendingImages = [
            ['marker' => 'rId1', 'relationship_id' => 'rId1', 'attributes' => ['inline' => true]],
            ['marker' => 'rId2', 'relationship_id' => 'rId2', 'attributes' => ['inline' => true]],
        ];

        $html = $this->layout->applyFlowingImageLayout($figureA.$figureB, $pendingImages);

        $this->assertStringContainsString('doc-image-grid', $html);
        $this->assertStringContainsString('--doc-image-grid-cols:2', $html);
        $this->assertTrue($pendingImages[0]['attributes']['flowing'] ?? false);
    }

    public function test_marks_complex_layout_images_as_unplaced(): void
    {
        $figure = '<figure class="doc-image doc-image--inline" data-pending-marker="rId1"><img data-pending="1" alt="A" /></figure>';
        $html = '<div class="doc-symbol-row"><div class="doc-symbol-icons">'.$figure.'</div>'
            .'<div class="doc-textbox"><span style="color:#FFFFFF">Bolt</span></div></div>';
        $pendingImages = [
            ['marker' => 'rId1', 'relationship_id' => 'rId1', 'attributes' => ['inline' => true]],
        ];

        $result = $this->layout->applyFlowingImageLayout($html, $pendingImages);

        $this->assertStringNotContainsString('<figure', $result);
        $this->assertTrue($pendingImages[0]['attributes']['unplaced'] ?? false);
    }

    public function test_normalizes_attributes_for_flowing_single_image(): void
    {
        $attributes = $this->layout->normalizeAttributesForFlow([
            'anchored' => true,
            'page_anchored' => false,
            'left_px' => 120,
            'top_px' => 40,
            'inline' => true,
        ]);

        $this->assertFalse($attributes['anchored'] ?? false);
        $this->assertFalse($attributes['inline'] ?? true);
        $this->assertTrue($attributes['flowing'] ?? false);
        $this->assertArrayNotHasKey('left_px', $attributes);
    }

    public function test_strips_anchored_callouts_but_keeps_symbol_row_text(): void
    {
        $figure = '<figure class="doc-image doc-image--inline" data-pending-marker="rId1"><img data-pending="1" alt="A" /></figure>';
        $html = '<div class="doc-symbol-row"><div class="doc-symbol-icons">'.$figure.'</div>'
            .'<div class="doc-textbox"><span>Keep bystanders away.</span></div></div>';
        $pendingImages = [
            ['marker' => 'rId1', 'relationship_id' => 'rId1', 'attributes' => ['inline' => true]],
        ];
        $plain = 'Keep bystanders away.';

        $result = $this->layout->applyFlowingImageLayout($html, $pendingImages, $plain);

        $this->assertStringContainsString('Keep bystanders away.', $result);
        $this->assertStringContainsString('doc-symbol-row', $result);
        $this->assertStringNotContainsString('<figure', $result);
        $this->assertSame('Keep bystanders away.', $plain);
    }

    public function test_strips_standalone_anchored_textboxes_for_unplaced_images(): void
    {
        $figure = '<figure class="doc-image doc-image--inline" data-pending-marker="rId1"><img data-pending="1" alt="A" /></figure>';
        $html = $figure.'<div class="doc-textbox doc-textbox--anchored"><span>3</span></div>';
        $pendingImages = [
            ['marker' => 'rId1', 'relationship_id' => 'rId1', 'attributes' => ['inline' => true]],
        ];
        $plain = '3';

        $result = $this->layout->applyFlowingImageLayout($html, $pendingImages, $plain);

        $this->assertSame('', $result);
        $this->assertSame('', $plain);
        $this->assertTrue($pendingImages[0]['attributes']['unplaced'] ?? false);
    }

    public function test_detects_image_grid_paragraph_class(): void
    {
        $html = '<div class="doc-image-grid"><figure class="doc-image"></figure></div>';

        $classes = $this->layout->paragraphClasses(
            pendingImages: [['marker' => 'rId1']],
            plain: '',
            innerHtml: $html,
        );

        $this->assertSame(['doc-paragraph--image-grid'], $classes);
    }
}

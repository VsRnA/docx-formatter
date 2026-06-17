<?php

namespace Tests\Unit;

use App\Infrastructure\Docx\Ooxml\Parsing\Layout\CoordinateFigureCanvasRenderer;
use App\Infrastructure\Docx\Ooxml\Parsing\Layout\FigureGroupGeometry;
use PHPUnit\Framework\TestCase;

final class CoordinateFigureCanvasRendererTest extends TestCase
{
    public function test_renders_absolute_positions_from_geometry_bbox(): void
    {
        $geometry = FigureGroupGeometry::fromItems([
            [
                'kind' => 'image',
                'left_px' => 0,
                'top_px' => 10,
                'width_px' => 100,
                'height_px' => 80,
                'html' => '<figure class="doc-image doc-image--inline" width="100" height="80"><img width="100" height="80" alt="" /></figure>',
                'marker' => 'rId1',
                'caption_label' => null,
            ],
            [
                'kind' => 'image',
                'left_px' => 146,
                'top_px' => 0,
                'width_px' => 176,
                'height_px' => 80,
                'html' => '<figure class="doc-image doc-image--inline" data-ooxml-left="130" data-ooxml-top="0" width="176" height="80"><img width="176" height="80" alt="" /></figure>',
                'marker' => 'rId2',
                'caption_label' => null,
            ],
            [
                'kind' => 'callout',
                'left_px' => 423,
                'top_px' => 60,
                'width_px' => 96,
                'height_px' => 24,
                'html' => '<strong>Locking knob</strong>',
                'marker' => null,
                'caption_label' => 'Locking knob',
            ],
        ]);

        $this->assertNotNull($geometry);
        $html = (new CoordinateFigureCanvasRenderer)->render($geometry);

        $this->assertStringContainsString('doc-figure-canvas', $html);
        $this->assertStringContainsString('doc-figure-canvas__layer', $html);
        $this->assertStringContainsString('left:0px;top:10px', $html);
        $this->assertStringContainsString('left:146px;top:0px', $html);
        $this->assertStringContainsString('data-ooxml-left="146"', $html);
        $this->assertStringContainsString('left:423px', $html);
        $this->assertStringContainsString('top:60px', $html);
        $this->assertStringContainsString('Locking knob', $html);
        $this->assertSame(2, substr_count($html, '<figcaption class="doc-figure-caption"'));
    }
}

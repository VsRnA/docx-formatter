<?php

namespace Tests\Unit;

use App\Infrastructure\Docx\Ooxml\Parsing\Layout\FigureGalleryBuilder;
use PHPUnit\Framework\TestCase;

final class FigureGalleryBuilderTest extends TestCase
{
    public function test_preserves_gaps_between_figures_from_caption_anchors(): void
    {
        $builder = new FigureGalleryBuilder;
        $html = $builder->build([
            $this->galleryRow(100, 80, 49, 'Bolt'),
            $this->galleryRow(100, 80, 195, 'Washer'),
            $this->galleryRow(100, 80, 300, 'Locking knob'),
        ]);

        $this->assertStringContainsString('margin-left:46px', $html);
        $this->assertStringContainsString('margin-left:5px', $html);
    }

    public function test_binds_each_caption_overlay_to_its_own_figure(): void
    {
        $builder = new FigureGalleryBuilder;
        $html = $builder->build([
            $this->galleryRow(100, 80, 49, 'Bolt'),
            $this->galleryRow(100, 80, 195, 'Washer'),
            $this->galleryRow(100, 80, 300, 'Locking knob'),
        ]);

        preg_match_all(
            '/<div class="doc-figure-overlay" style="([^"]*)">(.*?)<\/div>/',
            $html,
            $matches,
            PREG_SET_ORDER,
        );

        $this->assertCount(3, $matches);

        $expectedLocalLeft = [49, 49, 49];
        foreach ($matches as $index => $match) {
            $this->assertSame(1, preg_match('/left:(\d+)px/', $match[1], $leftMatch));
            $this->assertSame($expectedLocalLeft[$index], (int) $leftMatch[1]);
        }
    }

    public function test_positioned_gallery_uses_reconstructed_coordinates_for_overlays(): void
    {
        $builder = new FigureGalleryBuilder;
        $html = $builder->build(
            [
                $this->galleryRow(100, 80, 49, 'Bolt'),
                $this->galleryRow(100, 80, 195, 'Washer'),
            ],
            '<svg class="doc-anchor-shape" style="position:absolute;left:120px;top:10px;width:40px;height:20px"></svg>',
        );

        $this->assertStringContainsString('doc-figure-gallery--positioned', $html);
        $this->assertStringContainsString('left:49px', $html);
        $this->assertStringContainsString('left:195px', $html);
        $this->assertStringNotContainsString('left:300px', $html);
    }

    private function galleryRow(int $width, int $height, int $anchorLeft, string $label): string
    {
        return '<div class="doc-symbol-row">'
            .'<div class="doc-symbol-icons">'
            .'<figure class="doc-image doc-image--inline" width="'.$width.'" height="'.$height.'">'
            .'<img width="'.$width.'" height="'.$height.'" alt="" />'
            .'</figure>'
            .'</div>'
            .'<div class="doc-textbox" data-anchor-left="'.$anchorLeft.'" data-anchor-top="60">'
            .'<strong>'.$label.'</strong>'
            .'</div>'
            .'</div>';
    }
}

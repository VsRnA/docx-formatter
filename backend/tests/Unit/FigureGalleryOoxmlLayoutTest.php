<?php

namespace Tests\Unit;

use App\Infrastructure\Docx\Ooxml\Parsing\Layout\FigureGalleryBuilder;
use App\Infrastructure\Docx\Ooxml\Parsing\Layout\FigureGalleryOoxmlLayout;
use Tests\TestCase;

class FigureGalleryOoxmlLayoutTest extends TestCase
{
    public function test_uses_ooxml_left_positions_for_positioned_gallery(): void
    {
        $figureA = '<figure class="doc-image doc-image--inline" data-pending-marker="rId1" data-ooxml-left="0" data-ooxml-width="130" data-ooxml-height="173"><img width="130" height="173" /></figure>';
        $figureB = '<figure class="doc-image doc-image--inline" data-pending-marker="rId2" data-ooxml-left="130" data-ooxml-width="176" data-ooxml-height="151"><img width="176" height="151" /></figure>';
        $rowA = '<div class="doc-symbol-row"><div class="doc-symbol-icons">'.$figureA.'</div>'
            .'<div class="doc-textbox" data-anchor-left="49" data-anchor-top="148"><span style="color:#FFFFFF"><strong> Bolt</strong></span></div></div>';
        $rowB = '<div class="doc-symbol-row"><div class="doc-symbol-icons">'.$figureB.'</div>'
            .'<div class="doc-textbox" data-anchor-left="195" data-anchor-top="148"><span style="color:#FFFFFF"><strong> Washer</strong></span></div></div>';
        $shape = '<svg class="doc-anchor-shape doc-anchor-shape--line" style="position:absolute; left:248px; top:68px; width:26px; height:81px"><line x1="0" y1="81" x2="26" y2="0" stroke="#FFFFFF"/></svg>';

        $pendingImages = [
            ['marker' => 'rId1', 'attributes' => ['left_px' => 0, 'top_px' => 0, 'width_px' => 130, 'height_px' => 173]],
            ['marker' => 'rId2', 'attributes' => ['left_px' => 130, 'top_px' => 0, 'width_px' => 176, 'height_px' => 151]],
        ];

        $gallery = (new FigureGalleryBuilder)->build([$rowA, $rowB], $shape, $pendingImages);

        $this->assertStringContainsString('left:0px;top:0px', $gallery);
        $this->assertStringContainsString('left:146px;top:22px', $gallery);
        $this->assertStringContainsString('data-ooxml-left="0"', $figureA);
    }

    public function test_indexes_pending_images_by_marker(): void
    {
        $indexed = (new FigureGalleryOoxmlLayout)->indexByMarker([
            ['marker' => 'rId22', 'attributes' => ['left_px' => 0, 'top_px' => 0, 'width_px' => 130, 'height_px' => 173]],
        ]);

        $this->assertSame(0, $indexed['rId22']['left_px']);
        $this->assertSame(130, $indexed['rId22']['width_px']);
    }
}

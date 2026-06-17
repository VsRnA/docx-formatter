<?php

namespace Tests\Unit;

use App\Infrastructure\Document\Persist\BlockImageAssetService;
use Tests\TestCase;

class BlockImageAssetServiceTest extends TestCase
{
    public function test_preserves_gallery_layout_and_ooxml_attributes_when_resolving_pending_figure(): void
    {
        $original = '<figure style="position:absolute;left:130px;top:22px;margin:0;z-index:0" class="doc-image doc-image--inline" data-pending-marker="rId23" data-ooxml-left="130" data-ooxml-top="0" data-ooxml-width="176" data-ooxml-height="151"><img data-pending="1" width="176" height="151" /></figure>';
        $replacement = '<figure class="doc-image doc-image--inline"><img src="/uploaded.jpeg" width="176" height="151" style="width:176px; height:151px" /></figure>';

        $method = new \ReflectionMethod(BlockImageAssetService::class, 'replacePendingFigure');
        $method->setAccessible(true);

        $service = app(BlockImageAssetService::class);
        $html = '<div>'.$original.'</div>';
        $resolved = $method->invoke($service, $html, 'rId23', $replacement);

        $this->assertStringContainsString('position:absolute', $resolved);
        $this->assertStringContainsString('left:130px', $resolved);
        $this->assertStringContainsString('top:22px', $resolved);
        $this->assertStringContainsString('data-ooxml-left="130"', $resolved);
        $this->assertStringContainsString('data-ooxml-width="176"', $resolved);
        $this->assertStringContainsString('src="/uploaded.jpeg"', $resolved);
    }
}

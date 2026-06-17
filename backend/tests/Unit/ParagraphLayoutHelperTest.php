<?php

namespace Tests\Unit;

use App\Domain\Docx\Service\FigureGalleryCaptionMerger;
use App\Domain\Docx\ValueObject\BlockType;
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

    public function test_builds_figure_gallery_with_overlay_labels(): void
    {
        $figureA = '<figure class="doc-image doc-image--inline" data-pending-marker="rId1"><img data-pending="1" alt="A" width="130" height="173" style="width:130px; height:173px" /></figure>';
        $figureB = '<figure class="doc-image doc-image--inline" data-pending-marker="rId2"><img data-pending="1" alt="B" width="176" height="151" style="width:176px; height:151px" /></figure>';
        $rowA = '<div class="doc-symbol-row"><div class="doc-symbol-icons">'.$figureA.'</div>'
            .'<div class="doc-textbox" data-anchor-left="49" data-anchor-top="148"><span style="color:#FFFFFF"><strong> Bolt</strong></span></div></div>';
        $rowB = '<div class="doc-symbol-row"><div class="doc-symbol-icons">'.$figureB.'</div>'
            .'<div class="doc-textbox" data-anchor-left="195" data-anchor-top="148"><span style="color:#FFFFFF"><strong> Washer</strong></span></div></div>';
        $html = $rowA.$rowB;

        $gallery = $this->layout->buildFigureGalleryHtml($html);

        $this->assertNotNull($gallery);
        $this->assertStringContainsString('doc-figure-gallery', $gallery);
        $this->assertStringContainsString('doc-figure-overlay', $gallery);
        $this->assertStringContainsString('Bolt', $gallery);
        $this->assertStringContainsString('Washer', $gallery);
        $this->assertStringContainsString('left:49px', $gallery);
        $this->assertStringContainsString('doc-figure-caption', $gallery);
    }

    public function test_builds_single_figure_gallery_cell(): void
    {
        $figure = '<figure class="doc-image doc-image--inline"><img alt="Clamp" width="173" height="189" style="width:173px; height:189px" /></figure>';
        $row = '<div class="doc-symbol-row"><div class="doc-symbol-icons">'.$figure.'</div>'
            .'<div class="doc-textbox"><span style="color:#FFFFFF"><strong> Cable Clamp</strong></span></div></div>';

        $gallery = $this->layout->buildFigureGalleryHtml($row);

        $this->assertNotNull($gallery);
        $this->assertStringContainsString('doc-figure-gallery--single', $gallery);
        $this->assertStringContainsString('Cable Clamp', $gallery);
    }

    public function test_does_not_flatten_warning_symbol_rows_with_visible_text(): void
    {
        $figure = '<figure class="doc-image doc-image--inline"><img alt="Warning" /></figure>';
        $row = '<div class="doc-symbol-row"><div class="doc-symbol-icons">'.$figure.'</div>'
            .'<div class="doc-textbox"><span style="color:#000000">Do not touch</span></div></div>';

        $this->assertNull($this->layout->buildFigureGalleryHtml($row));
        $this->assertFalse($this->layout->isFigureGallerySymbolRow($row));
    }

    public function test_builds_positioned_gallery_when_shapes_present(): void
    {
        $figureA = '<figure class="doc-image doc-image--inline"><img alt="A" width="130" height="173" style="width:130px; height:173px" /></figure>';
        $figureB = '<figure class="doc-image doc-image--inline"><img alt="B" width="176" height="151" style="width:176px; height:151px" /></figure>';
        $rowA = '<div class="doc-symbol-row"><div class="doc-symbol-icons">'.$figureA.'</div>'
            .'<div class="doc-textbox" data-anchor-left="49" data-anchor-top="148"><span style="color:#FFFFFF"><strong> Bolt</strong></span></div></div>';
        $rowB = '<div class="doc-symbol-row"><div class="doc-symbol-icons">'.$figureB.'</div>'
            .'<div class="doc-textbox" data-anchor-left="195" data-anchor-top="148"><span style="color:#FFFFFF"><strong> Washer</strong></span></div></div>';
        $shape = '<svg class="doc-anchor-shape doc-anchor-shape--line" style="position:absolute; left:248px; top:68px; width:26px; height:81px"><line x1="0" y1="81" x2="26" y2="0" stroke="#FFFFFF"/></svg>';
        $builder = new \App\Infrastructure\Docx\Ooxml\Parsing\Layout\FigureGalleryBuilder;
        $gallery = $builder->build([$rowA, $rowB], $shape);

        $this->assertStringContainsString('doc-figure-gallery--positioned', $gallery);
        $this->assertStringContainsString('doc-figure-gallery__canvas', $gallery);
        $this->assertStringContainsString('doc-figure-gallery__captions', $gallery);
        $this->assertStringContainsString('doc-figure-caption-cell', $gallery);
        $this->assertStringContainsString('position:absolute;left:0px;top:', $gallery);
        $this->assertStringContainsString('left:49px', $gallery);
        $this->assertStringContainsString('left:195px', $gallery);
        $this->assertStringContainsString('top:148px', $gallery);
    }

    public function test_prefers_figure_gallery_class_for_gallery_html(): void
    {
        $html = '<div class="doc-figure-gallery"><figure class="doc-figure-cell"></figure></div>';

        $classes = $this->layout->paragraphClasses(
            pendingImages: [['marker' => 'rId1']],
            plain: 'Bolt',
            innerHtml: $html,
        );

        $this->assertSame(['doc-paragraph--figure-gallery'], $classes);
    }
}

class FigureGalleryCaptionMergerTest extends TestCase
{
    public function test_attaches_centered_captions_to_figure_cells(): void
    {
        $gallery = new \App\Domain\Docx\Entity\ParsedBlock(
            type: BlockType::Paragraph,
            sort: 1,
            html: '<div class="doc-figure-gallery"><figure class="doc-figure-cell"><figcaption class="doc-figure-caption"></figcaption></figure>'
                .'<figure class="doc-figure-cell"><figcaption class="doc-figure-caption"></figcaption></figure></div>',
            textOriginal: 'Bolt Washer',
        );
        $captions = new \App\Domain\Docx\Entity\ParsedBlock(
            type: BlockType::Paragraph,
            sort: 2,
            html: '<p><span style="font-size:10.5pt">Fig.2A </span><span style="font-size:10.5pt">Fig.2B </span></p>',
            textOriginal: 'Fig.2A Fig.2B',
        );

        $merged = (new FigureGalleryCaptionMerger)->merge([$gallery, $captions]);

        $this->assertCount(1, $merged);
        $this->assertStringContainsString('Fig.2A', (string) $merged[0]->html);
        $this->assertStringContainsString('Fig.2B', (string) $merged[0]->html);
        $this->assertStringContainsString('doc-figure-caption', (string) $merged[0]->html);
    }

    public function test_preserves_figcaption_layout_attributes(): void
    {
        $gallery = new \App\Domain\Docx\Entity\ParsedBlock(
            type: BlockType::Paragraph,
            sort: 1,
            html: '<div class="doc-figure-gallery doc-figure-gallery--positioned">'
                .'<figcaption class="doc-figure-caption" style="position:absolute;left:0px;top:194px;width:130px"></figcaption>'
                .'<figcaption class="doc-figure-caption" style="position:absolute;left:130px;top:194px;width:176px"></figcaption>'
                .'</div>',
            textOriginal: 'Bolt Washer',
        );
        $captions = new \App\Domain\Docx\Entity\ParsedBlock(
            type: BlockType::Paragraph,
            sort: 2,
            html: '<p><span style="font-size:10.5pt">Fig.2A </span><span style="font-size:10.5pt">Fig.2B </span></p>',
            textOriginal: 'Fig.2A Fig.2B',
        );

        $merged = (new FigureGalleryCaptionMerger)->merge([$gallery, $captions]);

        $this->assertStringContainsString('style="position:absolute;left:0px;top:194px;width:130px"', (string) $merged[0]->html);
        $this->assertStringContainsString('style="position:absolute;left:130px;top:194px;width:176px"', (string) $merged[0]->html);
    }

    public function test_merges_spaced_figure_labels_like_fig_4a(): void
    {
        $gallery = new \App\Domain\Docx\Entity\ParsedBlock(
            type: BlockType::Paragraph,
            sort: 1,
            html: '<div class="doc-figure-canvas"><div class="doc-figure-canvas__captions">'
                .'<figure class="doc-figure-caption-cell"><figcaption class="doc-figure-caption"></figcaption></figure>'
                .'<figure class="doc-figure-caption-cell"><figcaption class="doc-figure-caption"></figcaption></figure>'
                .'</div></div>',
            textOriginal: null,
        );
        $captions = new \App\Domain\Docx\Entity\ParsedBlock(
            type: BlockType::Paragraph,
            sort: 2,
            html: '<p><span>Fig.</span> <span>4</span><span>A</span> <span>Fig.</span> <span>4</span><span>B</span></p>',
            textOriginal: 'Fig. 4A Fig. 4B',
        );

        $merged = (new FigureGalleryCaptionMerger)->merge([$gallery, $captions]);

        $this->assertCount(1, $merged);
        $this->assertStringContainsString('Fig. 4A', (string) $merged[0]->html);
        $this->assertStringContainsString('Fig. 4B', (string) $merged[0]->html);
    }
}

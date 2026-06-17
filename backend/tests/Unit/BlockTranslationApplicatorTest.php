<?php

namespace Tests\Unit;

use App\Domain\Docx\Entity\ParsedBlock;
use App\Domain\Docx\Port\TranslatorPort;
use App\Domain\Docx\ValueObject\BlockType;
use App\Models\Document;
use App\Services\Ai\MockTranslationService;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlHtmlSegmentAnnotator;
use App\Infrastructure\Document\Persist\BlockTranslationApplicator;
use App\Infrastructure\Document\Translation\SegmentTranslationCoordinator;
use App\Infrastructure\Document\Translation\TranslatedHtmlPatcher;
use Tests\TestCase;

class BlockTranslationApplicatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mock.translate_enabled' => true]);
    }

    private function makeApplicator(?TranslatorPort $translator = null): BlockTranslationApplicator
    {
        $translator ??= new MockTranslationService;
        $segmentHtml = new OoxmlHtmlSegmentAnnotator;
        $htmlPatcher = new TranslatedHtmlPatcher;

        return new BlockTranslationApplicator(
            $translator,
            new SegmentTranslationCoordinator($translator, $segmentHtml, $htmlPatcher),
            $htmlPatcher,
        );
    }

    public function test_does_not_duplicate_rich_caption_html(): void
    {
        $document = new Document;
        $document->language_from = 'ru';
        $document->language_to = 'en';

        $html = '<p style="text-align: center"><span style="font-size:14pt"><strong>Рисунок </strong></span>'
            .'<span style="font-size:14pt"><strong>1</strong></span>'
            .'<span style="font-size:14pt"><strong>. Детальная визуализация архитектуры сети</strong></span></p>';

        $dto = new ParsedBlock(
            type: BlockType::Paragraph,
            sort: 1,
            html: $html,
            textOriginal: 'Рисунок 1. Детальная визуализация архитектуры сети',
        );

        $result = $this->makeApplicator()
            ->apply($document, $dto, $html, true);

        $this->assertStringContainsString('Рисунок 1. Детальная визуализация архитектуры сети', strip_tags((string) $result['html']));
        $this->assertSame(1, substr_count(strip_tags((string) $result['html']), 'Рисунок 1. Детальная визуализация архитектуры сети'));
    }

    public function test_applies_translation_to_rich_single_span_paragraph(): void
    {
        $document = new Document;
        $document->language_from = 'en';
        $document->language_to = 'ru';

        $html = '<p style="text-align: center"><span style="font-size:26pt"><strong>INTENDED USE</strong></span></p>';
        $dto = new ParsedBlock(
            type: BlockType::Paragraph,
            sort: 1,
            html: $html,
            textOriginal: 'INTENDED USE',
        );

        $translator = new class implements \App\Domain\Docx\Port\TranslatorPort
        {
            public function translate(string $text, string $from = 'en', string $to = 'ru'): string
            {
                return 'ПРЕДНАЗНАЧЕНИЕ';
            }

            public function translateMany(array $texts, string $from = 'en', string $to = 'ru'): array
            {
                return array_map(fn (string $text): string => $this->translate($text, $from, $to), $texts);
            }
        };

        $result = $this->makeApplicator($translator)
            ->apply($document, $dto, $html, true);

        $this->assertSame('ПРЕДНАЗНАЧЕНИЕ', strip_tags((string) $result['html']));
        $this->assertStringContainsString('<strong>', (string) $result['html']);
    }

    public function test_translates_textbox_segments_while_preserving_symbol_row_layout(): void
    {
        $document = new Document;
        $document->language_from = 'en';
        $document->language_to = 'ru';

        $html = '<p class="doc-paragraph--symbol-row">'
            .'<figure class="doc-image doc-image--inline" data-pending-marker="rId5"><img data-pending="1" alt="" /></figure>'
            .'<span class="doc-textbox"><span data-ooxml-seg="0">Keep bystanders away.</span></span>'
            .'</p>';

        $dto = new ParsedBlock(
            type: BlockType::Paragraph,
            sort: 1,
            html: $html,
            textOriginal: 'Keep bystanders away.',
            meta: [
                'ooxml_segments' => [
                    [
                        'id' => 0,
                        'text' => 'Keep bystanders away.',
                        't_indices' => [0],
                        'translatable' => true,
                    ],
                ],
            ],
        );

        $result = $this->makeApplicator()
            ->apply($document, $dto, $html, true);

        $this->assertStringContainsString('[RU]', strip_tags((string) $result['html']));
        $this->assertStringContainsString('data-ooxml-seg="0"', (string) $result['html']);
        $this->assertStringNotContainsString('>Keep bystanders away.<', (string) $result['html']);
        $this->assertStringContainsString('doc-image', (string) $result['html']);
        $this->assertStringContainsString('doc-textbox', (string) $result['html']);
        $this->assertStringContainsString('[RU]', (string) ($result['meta']['ooxml_segment_translations'][0] ?? ''));
    }

    public function test_translates_warning_paragraph_after_removing_emf_placeholder(): void
    {
        $document = new Document;
        $document->language_from = 'en';
        $document->language_to = 'ru';

        $warning = 'Warning! Due to the high risk of bodily injury to the user.';
        $html = '<div style="text-align: left">'
            .'<figure class="doc-image doc-image--inline doc-image--unsupported" data-unsupported-format="emf">'
            .'<span class="doc-image__unsupported-icon">EMF</span>'
            .'<figcaption class="doc-image__unsupported-caption">Изображение EMF (формат не поддерживается браузером)</figcaption>'
            .'</figure><span style="font-size:10.5pt">'.$warning.'</span></div>';

        $dto = new ParsedBlock(
            type: BlockType::Paragraph,
            sort: 1,
            html: $html,
            textOriginal: $warning,
            meta: [
                'ooxml_segments' => [
                    [
                        'id' => 0,
                        'text' => $warning,
                        't_indices' => [0],
                        'translatable' => true,
                    ],
                ],
            ],
        );

        $result = $this->makeApplicator()->apply($document, $dto, $html, true);

        $visible = strip_tags((string) $result['html']);
        $this->assertStringNotContainsString('EMF', $visible);
        $this->assertStringNotContainsString('не поддерживается браузером', $visible);
        $this->assertStringContainsString('[RU]', $visible);
        $this->assertStringNotContainsString('doc-image--unsupported', (string) $result['html']);
    }

    public function test_translates_toc_title_but_preserves_leader_dots(): void
    {
        $document = new Document;
        $document->language_from = 'en';
        $document->language_to = 'ru';

        $dots = "\u{2003}………………..……………….………9";
        $html = '<p style="line-height: 1.5"><span data-ooxml-seg="0" style="font-size:14pt">SECTION 3 GENERAL IDENTIFICATION </span>'
            .'<span style="font-size:14pt">'.$dots.'</span></p>';

        $dto = new ParsedBlock(
            type: BlockType::Paragraph,
            sort: 1,
            html: $html,
            textOriginal: 'SECTION 3 GENERAL IDENTIFICATION '.$dots,
            meta: [
                'ooxml_segments' => [
                    [
                        'id' => 0,
                        'text' => 'SECTION 3 GENERAL IDENTIFICATION',
                        't_indices' => [0],
                        'translatable' => true,
                    ],
                    [
                        'id' => 1,
                        'text' => $dots,
                        't_indices' => [1],
                        'translatable' => false,
                    ],
                ],
            ],
        );

        $result = $this->makeApplicator()
            ->apply($document, $dto, $html, true);

        $this->assertStringContainsString('[RU]', (string) $result['html']);
        $this->assertStringContainsString($dots, (string) $result['html']);
        $this->assertStringNotContainsString('&emsp;', (string) $result['html']);
    }

    public function test_translates_table_cells_via_segments(): void
    {
        $document = new Document;
        $document->language_from = 'en';
        $document->language_to = 'ru';

        $html = '<table><tbody><tr>'
            .'<td><span data-ooxml-seg="0">Cell A</span></td>'
            .'<td><span data-ooxml-seg="1">Cell B</span></td>'
            .'</tr></tbody></table>';

        $dto = new ParsedBlock(
            type: BlockType::Table,
            sort: 1,
            html: $html,
            textOriginal: "Cell A | Cell B",
            meta: [
                'ooxml_table_cells' => [
                    [
                        'cell_index' => 0,
                        'segments' => [
                            ['id' => 0, 'text' => 'Cell A', 't_indices' => [0], 'translatable' => true],
                        ],
                    ],
                    [
                        'cell_index' => 1,
                        'segments' => [
                            ['id' => 1, 'text' => 'Cell B', 't_indices' => [0], 'translatable' => true],
                        ],
                    ],
                ],
            ],
        );

        $result = $this->makeApplicator()
            ->apply($document, $dto, $html, true);

        $this->assertStringContainsString('[RU]Cell A', (string) $result['html']);
        $this->assertStringContainsString('[RU]Cell B', (string) $result['html']);
    }

    public function test_translates_fragmented_rich_paragraph_via_dom_segments(): void
    {
        $document = new Document;
        $document->language_from = 'en';
        $document->language_to = 'ru';

        $html = '<p style="text-align: center"><span style="font-size:26pt"><strong>INTENDED</strong></span>'
            .'<span style="font-size:26pt"><strong> USE</strong></span></p>';

        $dto = new ParsedBlock(
            type: BlockType::Paragraph,
            sort: 1,
            html: $html,
            textOriginal: 'INTENDED USE',
            meta: [
                'ooxml_segments' => [
                    [
                        'id' => 0,
                        'text' => 'INTENDED USE',
                        't_indices' => [0, 1],
                        'translatable' => true,
                    ],
                ],
            ],
        );

        $result = $this->makeApplicator()
            ->apply($document, $dto, $html, true);

        $this->assertSame('[RU]INTENDED USE', trim(strip_tags((string) $result['html'])));
    }
}

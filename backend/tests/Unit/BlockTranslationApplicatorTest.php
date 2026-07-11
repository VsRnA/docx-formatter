<?php

namespace Tests\Unit;

use App\Domain\Docx\Entity\ParsedBlock;
use App\Domain\Docx\ValueObject\BlockType;
use App\Infrastructure\Document\Persist\BlockTranslationApplicator;
use App\Infrastructure\Document\Translation\SegmentTranslationCoordinator;
use App\Infrastructure\Document\Translation\TranslatedHtmlPatcher;
use App\Infrastructure\Document\Translation\TranslationCacheStore;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlHtmlSegmentAnnotator;
use App\Infrastructure\External\Ai\MockTranslationService;
use App\Models\Document;
use Tests\TestCase;

class BlockTranslationApplicatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mock.translate_enabled' => true]);
    }

    private function makeApplicator(): BlockTranslationApplicator
    {
        $segmentHtml = new OoxmlHtmlSegmentAnnotator;
        $htmlPatcher = new TranslatedHtmlPatcher;

        return new BlockTranslationApplicator(
            new SegmentTranslationCoordinator($segmentHtml, $htmlPatcher),
            $htmlPatcher,
        );
    }

    /**
     * @param  list<string>  $texts
     * @return array<string, string>
     */
    private function mockTranslations(array $texts, string $from = 'en', string $to = 'ru'): array
    {
        $translator = new MockTranslationService;
        $map = [];

        foreach ($texts as $text) {
            $key = TranslationCacheStore::normalizeTextKey($text);
            $map[$key] = $translator->translate($text, $from, $to);
        }

        return $map;
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

        $text = 'Рисунок 1. Детальная визуализация архитектуры сети';
        $result = $this->makeApplicator()
            ->apply($document, $dto, $html, true, $this->mockTranslations([$text], 'ru', 'en'));

        $this->assertStringContainsString($text, strip_tags((string) $result['html']));
        $this->assertSame(1, substr_count(strip_tags((string) $result['html']), $text));
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

        $translations = ['INTENDED USE' => 'ПРЕДНАЗНАЧЕНИЕ'];

        $result = $this->makeApplicator()
            ->apply($document, $dto, $html, true, $translations);

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
            ->apply(
                $document,
                $dto,
                $html,
                true,
                $this->mockTranslations(['Keep bystanders away.']),
            );

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

        $result = $this->makeApplicator()->apply(
            $document,
            $dto,
            $html,
            true,
            $this->mockTranslations([$warning]),
        );

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
            ->apply(
                $document,
                $dto,
                $html,
                true,
                $this->mockTranslations(['SECTION 3 GENERAL IDENTIFICATION']),
            );

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
            textOriginal: 'Cell A | Cell B',
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
            ->apply(
                $document,
                $dto,
                $html,
                true,
                $this->mockTranslations(['Cell A', 'Cell B']),
            );

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
            ->apply(
                $document,
                $dto,
                $html,
                true,
                $this->mockTranslations(['INTENDED USE']),
            );

        $this->assertSame('[RU]INTENDED USE', trim(strip_tags((string) $result['html'])));
    }
}

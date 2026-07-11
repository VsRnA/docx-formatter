<?php

namespace Tests\Unit;

use App\Domain\Docx\Entity\ParsedBlock;
use App\Domain\Docx\Entity\ParsedDocument;
use App\Domain\Docx\ValueObject\BlockType;
use App\Infrastructure\Document\Translation\DocumentTranslationCollector;
use Tests\TestCase;

class DocumentTranslationCollectorTest extends TestCase
{
    public function test_collects_legacy_text_from_block_without_segments(): void
    {
        $parsed = new ParsedDocument(
            title: 'Test',
            blocks: [
                new ParsedBlock(
                    type: BlockType::Paragraph,
                    sort: 1,
                    html: '<p>Hello</p>',
                    textOriginal: 'Hello',
                ),
            ],
        );

        $units = (new DocumentTranslationCollector)->collect($parsed);

        $this->assertSame([
            [
                'blockIndex' => 0,
                'segmentId' => 0,
                'text' => 'Hello',
                'translatable' => true,
            ],
        ], $units);
    }

    public function test_collects_paragraph_segments(): void
    {
        $parsed = new ParsedDocument(
            title: 'Test',
            blocks: [
                new ParsedBlock(
                    type: BlockType::Paragraph,
                    sort: 1,
                    html: '<p><span data-ooxml-seg="0">A</span></p>',
                    textOriginal: 'A',
                    meta: [
                        'ooxml_segments' => [
                            ['id' => 0, 'text' => 'A', 'translatable' => true],
                            ['id' => 1, 'text' => '...', 'translatable' => false],
                        ],
                    ],
                ),
            ],
        );

        $units = (new DocumentTranslationCollector)->collect($parsed);

        $this->assertCount(2, $units);
        $this->assertSame(0, $units[0]['blockIndex']);
        $this->assertSame(0, $units[0]['segmentId']);
        $this->assertSame('A', $units[0]['text']);
        $this->assertFalse($units[1]['translatable']);
    }

    public function test_collects_table_cell_segments(): void
    {
        $parsed = new ParsedDocument(
            title: 'Test',
            blocks: [
                new ParsedBlock(
                    type: BlockType::Table,
                    sort: 1,
                    html: '<table></table>',
                    textOriginal: 'Cell A | Cell B',
                    meta: [
                        'ooxml_table_cells' => [
                            [
                                'cell_index' => 0,
                                'segments' => [
                                    ['id' => 0, 'text' => 'Cell A', 'translatable' => true],
                                ],
                            ],
                            [
                                'cell_index' => 1,
                                'segments' => [
                                    ['id' => 1, 'text' => 'Cell B', 'translatable' => true],
                                ],
                            ],
                        ],
                    ],
                ),
            ],
        );

        $units = (new DocumentTranslationCollector)->collect($parsed);

        $this->assertCount(2, $units);
        $this->assertSame('Cell A', $units[0]['text']);
        $this->assertSame('Cell B', $units[1]['text']);
    }

    public function test_skips_blocks_without_text(): void
    {
        $parsed = new ParsedDocument(
            title: 'Test',
            blocks: [
                new ParsedBlock(
                    type: BlockType::Image,
                    sort: 1,
                    html: '<figure><img /></figure>',
                    textOriginal: null,
                ),
            ],
        );

        $units = (new DocumentTranslationCollector)->collect($parsed);

        $this->assertSame([], $units);
    }
}

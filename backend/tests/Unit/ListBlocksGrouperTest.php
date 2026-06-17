<?php

namespace Tests\Unit;

use App\Domain\Docx\Entity\ParsedBlock;
use App\Domain\Docx\Service\ListBlocksGrouper;
use App\Domain\Docx\ValueObject\BlockType;
use PHPUnit\Framework\TestCase;

class ListBlocksGrouperTest extends TestCase
{
    public function test_preserves_inline_markup_inside_list_items(): void
    {
        $blocks = [
            new ParsedBlock(
                type: BlockType::List,
                sort: 0,
                html: '<li><strong>Bold</strong> item</li>',
                textOriginal: 'Bold item',
            ),
            new ParsedBlock(
                type: BlockType::List,
                sort: 1,
                html: '<li><em>Italic</em> item</li>',
                textOriginal: 'Italic item',
            ),
        ];

        $grouped = (new ListBlocksGrouper)->group($blocks);

        $this->assertCount(1, $grouped);
        $this->assertStringContainsString('<strong>Bold</strong>', $grouped[0]->html);
        $this->assertStringContainsString('<em>Italic</em>', $grouped[0]->html);
        $this->assertStringNotContainsString('strip_tags', $grouped[0]->html);
    }

    public function test_uses_ordered_list_for_numbered_styles(): void
    {
        $blocks = [
            new ParsedBlock(
                type: BlockType::List,
                sort: 0,
                html: '<li>One</li>',
                textOriginal: 'One',
                meta: ['list_style' => 'Numbered List'],
            ),
            new ParsedBlock(
                type: BlockType::List,
                sort: 1,
                html: '<li>Two</li>',
                textOriginal: 'Two',
                meta: ['list_style' => 'Numbered List'],
            ),
        ];

        $grouped = (new ListBlocksGrouper)->group($blocks);

        $this->assertStringStartsWith('<ol class="doc-list doc-list--decimal">', $grouped[0]->html);
    }

    public function test_uses_ordered_list_for_decimal_marker(): void
    {
        $blocks = [
            new ParsedBlock(
                type: BlockType::List,
                sort: 0,
                html: '<li>One</li>',
                textOriginal: 'One',
                meta: ['list_marker' => 'decimal'],
            ),
            new ParsedBlock(
                type: BlockType::List,
                sort: 1,
                html: '<li>Two</li>',
                textOriginal: 'Two',
                meta: ['list_marker' => 'decimal'],
            ),
        ];

        $grouped = (new ListBlocksGrouper)->group($blocks);

        $this->assertStringStartsWith('<ol class="doc-list doc-list--decimal">', $grouped[0]->html);
    }

    public function test_uses_dash_marker_for_hyphen_lists(): void
    {
        $blocks = [
            new ParsedBlock(
                type: BlockType::List,
                sort: 0,
                html: '<li>First</li>',
                textOriginal: 'First',
                meta: ['list_marker' => 'dash'],
            ),
            new ParsedBlock(
                type: BlockType::List,
                sort: 1,
                html: '<li>Second</li>',
                textOriginal: 'Second',
                meta: ['list_marker' => 'dash'],
            ),
        ];

        $grouped = (new ListBlocksGrouper)->group($blocks);

        $this->assertStringContainsString('doc-list--dash', $grouped[0]->html);
    }
}

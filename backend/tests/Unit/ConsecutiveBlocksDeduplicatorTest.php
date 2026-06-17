<?php

namespace Tests\Unit;

use App\Domain\Docx\Entity\ParsedBlock;
use App\Domain\Docx\Service\ConsecutiveBlocksDeduplicator;
use App\Domain\Docx\ValueObject\BlockType;
use App\Domain\Docx\Service\Support\TextRunFragmentMerger;
use PHPUnit\Framework\TestCase;

class ConsecutiveBlocksDeduplicatorTest extends TestCase
{
    public function test_keeps_richer_html_when_plain_text_duplicates(): void
    {
        $blocks = [
            new ParsedBlock(
                type: BlockType::Paragraph,
                sort: 0,
                html: '<p>Same text</p>',
                textOriginal: 'Same text',
            ),
            new ParsedBlock(
                type: BlockType::Paragraph,
                sort: 1,
                html: '<p><strong>Same text</strong></p>',
                textOriginal: 'Same text',
            ),
        ];

        $result = (new ConsecutiveBlocksDeduplicator(new TextRunFragmentMerger))->deduplicate($blocks);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('<strong>', $result[0]->html);
    }
}

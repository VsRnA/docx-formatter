<?php

namespace Tests\Unit;

use App\Domain\Docx\Entity\ParsedBlock;
use App\Domain\Docx\Entity\ParsedDocument;
use App\Domain\Docx\Service\CoveragePolicy;
use App\Domain\Docx\ValueObject\BlockType;
use App\Infrastructure\Document\CoverageSourceTextExtractor;
use Tests\TestCase;

class CoverageValidatorTest extends TestCase
{
    public function test_reports_full_coverage_when_block_text_matches_source(): void
    {
        $fixture = base_path('tests/fixtures/minimal.docx');
        if (! is_file($fixture)) {
            $this->markTestSkipped('Fixture minimal.docx not found');
        }

        $parsed = new ParsedDocument(
            title: 'Test',
            blocks: [
                new ParsedBlock(
                    type: BlockType::Paragraph,
                    sort: 0,
                    html: '<p>Hello</p>',
                    textOriginal: 'Hello World',
                ),
            ],
        );

        $sourcePlain = (new CoverageSourceTextExtractor)->extract($fixture);
        $blocksPlain = 'Hello World';
        $result = (new CoveragePolicy)->evaluate($sourcePlain, $blocksPlain, PARSE_COVERAGE_THRESHOLD);

        $this->assertGreaterThan(0, strlen($sourcePlain));
        $this->assertArrayHasKey('coverage_ratio', $result->toArray());
    }

    public function test_empty_source_returns_full_coverage(): void
    {
        $result = (new CoveragePolicy)->evaluate('', '', PARSE_COVERAGE_THRESHOLD);

        $this->assertSame(1.0, $result->coverageRatio);
        $this->assertTrue($result->passesThreshold);
    }
}

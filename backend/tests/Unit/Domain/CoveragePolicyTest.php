<?php

namespace Tests\Unit\Domain;

use App\Domain\Docx\Service\CoveragePolicy;
use PHPUnit\Framework\TestCase;

final class CoveragePolicyTest extends TestCase
{
    public function test_full_coverage_when_texts_match(): void
    {
        $result = (new CoveragePolicy)->evaluate('Hello world', 'Hello world', 0.99);

        $this->assertSame(1.0, $result->coverageRatio);
        $this->assertTrue($result->passesThreshold);
    }

    public function test_empty_source_passes_with_ratio_one(): void
    {
        $result = (new CoveragePolicy)->evaluate('', 'some blocks', 0.99);

        $this->assertSame(1.0, $result->coverageRatio);
        $this->assertTrue($result->passesThreshold);
        $this->assertSame(0, $result->sourceCharCount);
    }

    public function test_low_coverage_fails_threshold(): void
    {
        $result = (new CoveragePolicy)->evaluate('abcdefghij', 'abc', 0.99);

        $this->assertFalse($result->passesThreshold);
        $this->assertLessThan(0.99, $result->coverageRatio);
    }
}

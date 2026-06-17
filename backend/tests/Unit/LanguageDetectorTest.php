<?php

namespace Tests\Unit;

use App\Services\Ai\Support\LanguageDetector;
use PHPUnit\Framework\TestCase;

class LanguageDetectorTest extends TestCase
{
    public function test_detects_cyrillic_and_latin_scripts(): void
    {
        $detector = new LanguageDetector;

        $this->assertTrue($detector->isLikely('Привет мир', 'ru'));
        $this->assertFalse($detector->isLikely('Hello world', 'ru'));
        $this->assertTrue($detector->isLikely('Hello world', 'en'));
    }

    public function test_skips_segments_without_letters(): void
    {
        $detector = new LanguageDetector;

        $this->assertTrue($detector->shouldSkipTranslation('123 / 456', 'ru'));
        $this->assertFalse($detector->shouldSkipTranslation('Hello', 'ru'));
    }
}

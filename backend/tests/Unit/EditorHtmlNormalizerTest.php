<?php

namespace Tests\Unit;

use App\Infrastructure\Document\EditorHtmlNormalizer;
use PHPUnit\Framework\TestCase;

class EditorHtmlNormalizerTest extends TestCase
{
    public function test_converts_quill_align_class_to_inline_style(): void
    {
        $html = '<p class="ql-align-center">Centered text</p>';

        $normalized = (new EditorHtmlNormalizer)->normalize($html);

        $this->assertStringContainsString('text-align: center', $normalized);
        $this->assertStringNotContainsString('ql-align-center', $normalized);
    }
}

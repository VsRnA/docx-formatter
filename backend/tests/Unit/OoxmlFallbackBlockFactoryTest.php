<?php

namespace Tests\Unit;

use App\Domain\Docx\ValueObject\BlockType;
use App\Domain\Docx\ValueObject\ParseContext;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlFallbackBlockFactory;
use App\Support\Constants\HtmlCssClasses;
use DOMDocument;
use Tests\TestCase;

class OoxmlFallbackBlockFactoryTest extends TestCase
{
    public function test_creates_html_raw_block_with_fragment(): void
    {
        $document = new DOMDocument;
        $document->loadXML(
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<w:altChunk xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" r:id="rId5"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"/>'
        );

        $element = $document->documentElement;
        $context = new ParseContext;
        $factory = new OoxmlFallbackBlockFactory;

        $block = $factory->create($element, $context, 3);

        $this->assertSame(BlockType::HtmlRaw, $block->type);
        $this->assertStringContainsString(HtmlCssClasses::DOC_RAW_OOXML, (string) $block->html);
        $this->assertSame('ooxml_fallback', $block->contentJson['kind'] ?? null);
        $this->assertSame('altChunk', $block->contentJson['localName'] ?? null);
        $this->assertNotEmpty($block->meta['ooxml_fragment'] ?? null);
        $this->assertSame(0, $block->meta['confidence'] ?? null);
    }
}

<?php

namespace Tests\Unit;

use App\Infrastructure\Docx\Ooxml\OoxmlNamespaces;
use App\Infrastructure\Docx\Ooxml\Styles\OoxmlNumberingResolver;
use DOMDocument;
use PHPUnit\Framework\TestCase;

class OoxmlNumberingResolverTest extends TestCase
{
    public function test_resolves_hyphen_bullet_for_symbol_marker(): void
    {
        $resolver = new OoxmlNumberingResolver;
        $resolver->load($this->numberingDocument());

        $this->assertSame('dash', $resolver->resolveMarker('6', '0'));
    }

    public function test_resolves_decimal_list(): void
    {
        $resolver = new OoxmlNumberingResolver;
        $resolver->load($this->numberingDocument());

        $this->assertSame('decimal', $resolver->resolveMarker('8', '0'));
    }

    private function numberingDocument(): DOMDocument
    {
        $w = OoxmlNamespaces::W;
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:numbering xmlns:w="{$w}">
  <w:abstractNum w:abstractNumId="0">
    <w:lvl w:ilvl="0">
      <w:numFmt w:val="bullet"/>
      <w:lvlText w:val="-"/>
      <w:rPr><w:rFonts w:ascii="Symbol"/></w:rPr>
    </w:lvl>
  </w:abstractNum>
  <w:abstractNum w:abstractNumId="2">
    <w:lvl w:ilvl="0">
      <w:numFmt w:val="decimal"/>
      <w:lvlText w:val="%1."/>
    </w:lvl>
  </w:abstractNum>
  <w:num w:numId="6"><w:abstractNumId w:val="0"/></w:num>
  <w:num w:numId="8"><w:abstractNumId w:val="2"/></w:num>
</w:numbering>
XML;

        $document = new DOMDocument;
        $document->loadXML($xml);

        return $document;
    }
}

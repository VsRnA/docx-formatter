<?php

namespace Tests\Unit;

use App\Infrastructure\Docx\Ooxml\OoxmlNamespaces;
use App\Infrastructure\Docx\Ooxml\Styles\OoxmlStyleResolver;
use DOMDocument;
use PHPUnit\Framework\TestCase;

class OoxmlStyleResolverTest extends TestCase
{
    public function test_resolves_heading_level_from_style_id(): void
    {
        $resolver = new OoxmlStyleResolver;
        $resolver->load($this->stylesDocument());

        $this->assertSame(1, $resolver->headingLevel('Heading1'));
        $this->assertSame(2, $resolver->headingLevel('Heading2'));
        $this->assertSame(1, $resolver->headingLevel('Title'));
    }

    public function test_builds_paragraph_css_from_style_chain(): void
    {
        $resolver = new OoxmlStyleResolver;
        $resolver->load($this->stylesDocument());

        $css = $resolver->paragraphCss(null, 'Heading1');

        $this->assertContains('text-align: center', $css);
    }

    public function test_run_defaults_inherit_bold_from_style(): void
    {
        $resolver = new OoxmlStyleResolver;
        $resolver->load($this->stylesDocument());

        $defaults = $resolver->runDefaults('Heading1');

        $this->assertTrue($defaults['bold']);
    }

    public function test_reads_document_defaults_from_doc_defaults(): void
    {
        $resolver = new OoxmlStyleResolver;
        $resolver->load($this->stylesDocumentWithDocDefaults());

        $defaults = $resolver->documentDefaults();

        $this->assertSame('Calibri', $defaults['font']);
        $this->assertSame(11.0, $defaults['size_pt']);
        $this->assertSame(1.08, $defaults['line_height']);
    }

    public function test_run_defaults_include_doc_defaults(): void
    {
        $resolver = new OoxmlStyleResolver;
        $resolver->load($this->stylesDocumentWithDocDefaults());

        $defaults = $resolver->runDefaults(null);

        $this->assertSame('Calibri', $defaults['font']);
        $this->assertSame(11.0, $defaults['sizePt']);
    }

    private function stylesDocumentWithDocDefaults(): \DOMDocument
    {
        $w = OoxmlNamespaces::W;
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:styles xmlns:w="{$w}">
  <w:docDefaults>
    <w:rPrDefault>
      <w:rPr>
        <w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/>
        <w:sz w:val="22"/>
      </w:rPr>
    </w:rPrDefault>
    <w:pPrDefault>
      <w:pPr>
        <w:spacing w:line="259" w:lineRule="auto"/>
      </w:pPr>
    </w:pPrDefault>
  </w:docDefaults>
</w:styles>
XML;

        $document = new \DOMDocument;
        $document->loadXML($xml);

        return $document;
    }

    private function stylesDocument(): \DOMDocument
    {
        $w = OoxmlNamespaces::W;
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:styles xmlns:w="{$w}">
  <w:style w:type="paragraph" w:styleId="Normal">
    <w:name w:val="Normal"/>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading1">
    <w:name w:val="heading 1"/>
    <w:basedOn w:val="Normal"/>
    <w:pPr><w:jc w:val="center"/></w:pPr>
    <w:rPr><w:b/></w:rPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading2">
    <w:name w:val="heading 2"/>
    <w:basedOn w:val="Heading1"/>
  </w:style>
</w:styles>
XML;

        $document = new DOMDocument;
        $document->loadXML($xml);

        return $document;
    }
}

<?php

namespace Tests\Unit;

use App\Infrastructure\Docx\Ooxml\OoxmlNamespaces;
use App\Infrastructure\Docx\Ooxml\Writing\OoxmlTextNodeIndex;
use App\Infrastructure\Docx\Ooxml\Writing\OoxmlTextSegmentCollector;
use DOMDocument;
use PHPUnit\Framework\TestCase;

final class OoxmlTextSegmentCollectorTest extends TestCase
{
    public function test_collects_textbox_segment_indices(): void
    {
        $document = new DOMDocument;
        $document->loadXML(<<<'XML'
<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
     xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
     xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
     xmlns:wps="http://schemas.microsoft.com/office/word/2010/wordprocessingShape">
  <w:r><w:drawing><wp:anchor><a:graphic><a:graphicData><wps:wsp><wps:txbx><wps:txbxContent>
    <w:p><w:r><w:t>Keep bystanders away.</w:t></w:r></w:p>
  </wps:txbxContent></wps:txbx></wps:wsp></a:graphicData></a:graphic></wp:anchor></w:drawing></w:r>
</w:p>
XML);

        $paragraph = $document->getElementsByTagNameNS(OoxmlNamespaces::W, 'p')->item(0);
        $this->assertNotNull($paragraph);

        $collector = new OoxmlTextSegmentCollector(new OoxmlTextNodeIndex);
        $segments = $collector->collectFromParagraph($paragraph);

        $this->assertCount(1, $segments);
        $this->assertSame('Keep bystanders away.', $segments[0]['text']);
        $this->assertTrue($segments[0]['translatable']);
        $this->assertSame([0], $segments[0]['t_indices']);
    }

    public function test_splits_table_of_contents_runs(): void
    {
        $document = new DOMDocument;
        $document->loadXML(<<<'XML'
<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:r><w:t>SECTION 3 GENERAL IDENTIFICATION </w:t></w:r>
  <w:r><w:t xml:space="preserve">&#x2003;………………..……………….………9</w:t></w:r>
</w:p>
XML);

        $paragraph = $document->getElementsByTagNameNS(OoxmlNamespaces::W, 'p')->item(0);
        $this->assertNotNull($paragraph);

        $collector = new OoxmlTextSegmentCollector(new OoxmlTextNodeIndex);
        $segments = $collector->collectFromParagraph($paragraph);

        $this->assertCount(2, $segments);
        $this->assertTrue($segments[0]['translatable']);
        $this->assertFalse($segments[1]['translatable']);
    }

    public function test_preserves_spaces_between_runs_in_segment_text(): void
    {
        $document = new DOMDocument;
        $document->loadXML(<<<'XML'
<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:r><w:t>Hello </w:t></w:r>
  <w:r><w:t>world</w:t></w:r>
</w:p>
XML);

        $paragraph = $document->getElementsByTagNameNS(OoxmlNamespaces::W, 'p')->item(0);
        $this->assertNotNull($paragraph);

        $collector = new OoxmlTextSegmentCollector(new OoxmlTextNodeIndex);
        $segments = $collector->collectFromParagraph($paragraph);

        $this->assertCount(1, $segments);
        $this->assertSame('Hello world', $segments[0]['text']);
    }
}

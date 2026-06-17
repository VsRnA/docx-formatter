<?php

namespace Tests\Unit;

use App\Infrastructure\Docx\Ooxml\OoxmlNamespaces;
use App\Infrastructure\Docx\Ooxml\Writing\OoxmlTextNodeIndex;
use App\Infrastructure\Docx\Ooxml\Writing\OoxmlTextReplacer;
use DOMDocument;
use PHPUnit\Framework\TestCase;

final class OoxmlTextReplacerTest extends TestCase
{
    public function test_replaces_first_text_node_and_clears_duplicates(): void
    {
        $document = new DOMDocument;
        $document->loadXML(<<<'XML'
<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:r><w:t>Old</w:t></w:r>
  <w:r><w:rPr><w:b/></w:rPr><w:t>Old</w:t></w:r>
</w:p>
XML);

        $paragraph = $document->getElementsByTagNameNS(OoxmlNamespaces::W, 'p')->item(0);
        $this->assertNotNull($paragraph);

        $replacer = new OoxmlTextReplacer(new OoxmlTextNodeIndex);
        $this->assertTrue($replacer->replaceInParagraph($paragraph, 'New text'));

        $texts = [];
        foreach ($paragraph->getElementsByTagNameNS(OoxmlNamespaces::W, 't') as $node) {
            $texts[] = $node->textContent;
        }

        $this->assertSame(['New text', ''], $texts);
    }

    public function test_replace_segments_clears_duplicate_original_runs(): void
    {
        $document = new DOMDocument;
        $document->loadXML(<<<'XML'
<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:r><w:t>Warning</w:t></w:r>
  <w:r><w:rPr><w:b/></w:rPr><w:t>Warning</w:t></w:r>
</w:p>
XML);

        $paragraph = $document->getElementsByTagNameNS(OoxmlNamespaces::W, 'p')->item(0);
        $this->assertNotNull($paragraph);

        $replacer = new OoxmlTextReplacer(new OoxmlTextNodeIndex);
        $this->assertTrue($replacer->replaceSegments($paragraph, [
            [
                'id' => 0,
                'text' => 'Warning',
                't_indices' => [0, 1],
                'translatable' => true,
            ],
        ], [
            0 => 'Внимание',
        ]));

        $texts = [];
        foreach ($paragraph->getElementsByTagNameNS(OoxmlNamespaces::W, 't') as $node) {
            $texts[] = $node->textContent;
        }

        $this->assertSame(['Внимание', ''], $texts);
        $this->assertStringNotContainsString('Warning', $paragraph->C14N());
    }
}

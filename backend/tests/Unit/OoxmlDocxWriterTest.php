<?php

namespace Tests\Unit;

use App\Enums\BlockType;
use App\Enums\TranslationStatus;
use App\Models\Document;
use App\Models\DocumentBlock;
use App\Infrastructure\Docx\Ooxml\OoxmlDocxWriter;
use App\Infrastructure\Docx\Ooxml\OoxmlPackage;
use App\Infrastructure\Docx\Ooxml\Writing\OoxmlPackageWriter;
use App\Infrastructure\Docx\Ooxml\Writing\OoxmlTextNodeIndex;
use App\Infrastructure\Docx\Ooxml\Writing\OoxmlTextReplacer;
use App\Infrastructure\Docx\Ooxml\Writing\OoxmlTextScopeWalker;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class OoxmlDocxWriterTest extends TestCase
{
    private string $sourcePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sourcePath = $this->createDocx();
    }

    protected function tearDown(): void
    {
        if (is_file($this->sourcePath)) {
            unlink($this->sourcePath);
        }

        parent::tearDown();
    }

    public function test_writes_translated_text_into_document_xml(): void
    {
        $outputPath = sys_get_temp_dir().'/ooxml-write-'.uniqid('', true).'.docx';
        $document = $this->makeDocumentWithBlocks([
            [
                'type' => BlockType::Paragraph,
                'sort' => 0,
                'text_original' => 'Hello world',
                'text_translated' => 'Привет мир',
                'meta_json' => ['ooxml_scope_index' => 0],
            ],
        ]);

        $writer = new OoxmlDocxWriter(
            new OoxmlTextScopeWalker,
            new OoxmlTextReplacer(new OoxmlTextNodeIndex),
            new OoxmlPackageWriter,
        );

        try {
            $stats = $writer->writeFromDocument($document, $this->sourcePath, $outputPath);

            $this->assertSame(1, $stats['scopes_updated']);
            $this->assertStringContainsString('Привет мир', $this->readDocumentXml($outputPath));
            $this->assertStringNotContainsString('Hello world', $this->readDocumentXml($outputPath));
        } finally {
            if (is_file($outputPath)) {
                unlink($outputPath);
            }
        }
    }

    public function test_patches_blocks_marked_as_edited_in_editor(): void
    {
        $outputPath = sys_get_temp_dir().'/ooxml-write-edited-'.uniqid('', true).'.docx';
        $document = $this->makeDocumentWithBlocks([
            [
                'type' => BlockType::Paragraph,
                'sort' => 0,
                'text_original' => 'Hello world',
                'text_translated' => null,
                'html' => '<p>Changed text</p>',
                'meta_json' => ['ooxml_scope_index' => 0, 'content_edited' => true],
            ],
        ]);

        $writer = new OoxmlDocxWriter(
            new OoxmlTextScopeWalker,
            new OoxmlTextReplacer(new OoxmlTextNodeIndex),
            new OoxmlPackageWriter,
        );

        $this->assertTrue($writer->documentNeedsPatch($document));

        try {
            $stats = $writer->writeFromDocument($document, $this->sourcePath, $outputPath);

            $this->assertSame(1, $stats['scopes_updated']);
            $this->assertStringContainsString('Changed text', $this->readDocumentXml($outputPath));
        } finally {
            if (is_file($outputPath)) {
                unlink($outputPath);
            }
        }
    }

    public function test_does_not_patch_blocks_without_translation_or_edits(): void
    {
        $outputPath = sys_get_temp_dir().'/ooxml-write-unchanged-'.uniqid('', true).'.docx';
        $document = $this->makeDocumentWithBlocks([
            [
                'type' => BlockType::Paragraph,
                'sort' => 0,
                'text_original' => 'Hello world',
                'text_translated' => null,
                'meta_json' => ['ooxml_scope_index' => 0],
            ],
        ]);

        $writer = new OoxmlDocxWriter(
            new OoxmlTextScopeWalker,
            new OoxmlTextReplacer(new OoxmlTextNodeIndex),
            new OoxmlPackageWriter,
        );

        $this->assertFalse($writer->documentNeedsPatch($document));

        try {
            $stats = $writer->writeFromDocument($document, $this->sourcePath, $outputPath);

            $this->assertSame(0, $stats['scopes_updated']);
            $this->assertStringContainsString('Hello world', $this->readDocumentXml($outputPath));
        } finally {
            if (is_file($outputPath)) {
                unlink($outputPath);
            }
        }
    }

    public function test_writes_segment_translations_into_textbox(): void
    {
        $sourcePath = $this->createTextboxDocx();
        $outputPath = sys_get_temp_dir().'/ooxml-textbox-'.uniqid('', true).'.docx';
        $document = $this->makeDocumentWithBlocks([
            [
                'type' => BlockType::Paragraph,
                'sort' => 0,
                'text_original' => 'Keep bystanders away.',
                'text_translated' => 'Держитесь подальше.',
                'meta_json' => [
                    'ooxml_scope_index' => 0,
                    'ooxml_segments' => [
                        [
                            'id' => 0,
                            'text' => 'Keep bystanders away.',
                            't_indices' => [0],
                            'translatable' => true,
                        ],
                    ],
                    'ooxml_segment_translations' => [
                        0 => 'Держитесь подальше.',
                    ],
                ],
            ],
        ]);

        $writer = new OoxmlDocxWriter(
            new OoxmlTextScopeWalker,
            new OoxmlTextReplacer(new OoxmlTextNodeIndex),
            new OoxmlPackageWriter,
        );

        try {
            $stats = $writer->writeFromDocument($document, $sourcePath, $outputPath);

            $this->assertSame(1, $stats['scopes_updated']);
            $xml = $this->readDocumentXml($outputPath);
            $this->assertStringContainsString('Держитесь подальше.', $xml);
            $this->assertStringNotContainsString('Keep bystanders away.', $xml);
        } finally {
            if (is_file($sourcePath)) {
                unlink($sourcePath);
            }
            if (is_file($outputPath)) {
                unlink($outputPath);
            }
        }
    }

    public function test_writes_table_cells_from_block_html(): void
    {
        $tableDocx = $this->createTableDocx();
        $outputPath = sys_get_temp_dir().'/ooxml-table-'.uniqid('', true).'.docx';
        $document = $this->makeDocumentWithBlocks([
            [
                'type' => BlockType::Table,
                'sort' => 0,
                'text_original' => "Cell A | Cell B\nRow2 A | Row2 B",
                'text_translated' => null,
                'html' => '<table><tbody><tr><td>Alpha</td><td>Beta</td></tr><tr><td>Gamma</td><td>Delta</td></tr></tbody></table>',
                'meta_json' => ['ooxml_scope_index' => 0, 'content_edited' => true],
            ],
        ]);

        $writer = new OoxmlDocxWriter(
            new OoxmlTextScopeWalker,
            new OoxmlTextReplacer(new OoxmlTextNodeIndex),
            new OoxmlPackageWriter,
        );

        try {
            $stats = $writer->writeFromDocument($document, $tableDocx, $outputPath);

            $this->assertSame(1, $stats['scopes_updated']);
            $xml = $this->readDocumentXml($outputPath);
            $this->assertStringContainsString('Alpha', $xml);
            $this->assertStringContainsString('Delta', $xml);
        } finally {
            if (is_file($tableDocx)) {
                unlink($tableDocx);
            }
            if (is_file($outputPath)) {
                unlink($outputPath);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    private function makeDocumentWithBlocks(array $blocks): Document
    {
        $document = new Document;
        $document->id = '00000000-0000-4000-8000-000000000001';

        $document->setRelation('blocks', collect(array_map(function (array $data): DocumentBlock {
            $block = new DocumentBlock;
            $block->type = $data['type'];
            $block->sort = $data['sort'];
            $block->text_original = $data['text_original'] ?? null;
            $block->text_translated = $data['text_translated'] ?? null;
            $block->html = $data['html'] ?? '<p>'.($data['text_original'] ?? '').'</p>';
            $block->meta_json = $data['meta_json'] ?? [];
            $block->translation_status = TranslationStatus::Done;

            return $block;
        }, $blocks)));

        return $document;
    }

    private function readDocumentXml(string $path): string
    {
        $package = new OoxmlPackage($path);
        try {
            return $package->document()->saveXML() ?: '';
        } finally {
            $package->close();
        }
    }

    private function createTextboxDocx(): string
    {
        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
            xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
            xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
            xmlns:wps="http://schemas.microsoft.com/office/word/2010/wordprocessingShape">
  <w:body>
    <w:p>
      <w:r>
        <w:drawing>
          <wp:anchor>
            <a:graphic>
              <a:graphicData>
                <wps:wsp>
                  <wps:txbx>
                    <wps:txbxContent>
                      <w:p><w:r><w:t>Keep bystanders away.</w:t></w:r></w:p>
                    </wps:txbxContent>
                  </wps:txbx>
                </wps:wsp>
              </a:graphicData>
            </a:graphic>
          </wp:anchor>
        </w:drawing>
      </w:r>
    </w:p>
    <w:sectPr/>
  </w:body>
</w:document>
XML;

        return $this->writeDocx([
            'word/document.xml' => $documentXml,
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>',
        ]);
    }

    private function createDocx(): string
    {
        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Hello world</w:t></w:r></w:p>
    <w:sectPr/>
  </w:body>
</w:document>
XML;

        return $this->writeDocx([
            'word/document.xml' => $documentXml,
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>',
        ]);
    }

    private function createTableDocx(): string
    {
        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:tbl>
      <w:tr>
        <w:tc><w:p><w:r><w:t>Cell A</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>Cell B</w:t></w:r></w:p></w:tc>
      </w:tr>
      <w:tr>
        <w:tc><w:p><w:r><w:t>Row2 A</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>Row2 B</w:t></w:r></w:p></w:tc>
      </w:tr>
    </w:tbl>
    <w:sectPr/>
  </w:body>
</w:document>
XML;

        return $this->writeDocx([
            'word/document.xml' => $documentXml,
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>',
        ]);
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function writeDocx(array $entries): string
    {
        $path = sys_get_temp_dir().'/ooxml-write-src-'.uniqid('', true).'.docx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return $path;
    }
}

<?php

namespace Tests\Feature;

use App\Application\Document\Processing\DocumentProcessingPipeline;
use App\Domain\Shared\Port\FileStoragePort;
use App\Enums\BlockType;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Support\Constants\ProcessingStages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

final class DocumentProcessingPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.integrations.mock_storage' => true,
            'services.integrations.mock_translation' => true,
            'services.docx.write_translated_docx' => false,
        ]);
    }

    public function test_pipeline_parses_blocks_and_builds_html_draft(): void
    {
        $docxPath = $this->createMinimalDocx();
        $storageKey = 'uploads/pipeline-test.docx';

        try {
            $storage = app(FileStoragePort::class);
            $storage->put($storageKey, (string) file_get_contents($docxPath), 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

            $document = Document::query()->create([
                'title' => 'Pipeline Test',
                'slug' => 'pipeline-test-'.uniqid('', true),
                'source_file_key' => $storageKey,
                'status' => DocumentStatus::Uploading,
                'meta_json' => ['translate' => false],
            ]);

            app(DocumentProcessingPipeline::class)->run($document->id);

            $document = Document::query()->with('blocks')->findOrFail($document->id);

            $this->assertSame(DocumentStatus::Ready, $document->status);
            $this->assertSame(ProcessingStages::COMPLETED, $document->processing_stage);
            $this->assertNotEmpty($document->html_draft);
            $this->assertGreaterThanOrEqual(3, $document->blocks->count());

            $types = $document->blocks->pluck('type')->all();
            $this->assertContains(BlockType::Heading, $types);
            $this->assertContains(BlockType::Paragraph, $types);
            $this->assertContains(BlockType::Table, $types);

            $this->assertArrayHasKey('parse_coverage', $document->meta_json ?? []);
            $this->assertArrayHasKey('ai_normalize', $document->meta_json ?? []);
            $this->assertStringContainsString('Test Title', $document->html_draft);
        } finally {
            if (is_file($docxPath)) {
                unlink($docxPath);
            }
        }
    }

    private function createMinimalDocx(): string
    {
        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:pPr><w:pStyle w:val="Heading1"/></w:pPr>
      <w:r><w:rPr><w:b/></w:rPr><w:t>Test Title</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t>Body paragraph text.</w:t></w:r>
    </w:p>
    <w:tbl>
      <w:tr>
        <w:tc><w:p><w:r><w:t>Cell A</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>Cell B</w:t></w:r></w:p></w:tc>
      </w:tr>
    </w:tbl>
    <w:sectPr/>
  </w:body>
</w:document>
XML;

        $path = sys_get_temp_dir().'/pipeline-test-'.uniqid('', true).'.docx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->addFromString(
            'word/_rels/document.xml.rels',
            '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>',
        );
        $zip->close();

        return $path;
    }
}

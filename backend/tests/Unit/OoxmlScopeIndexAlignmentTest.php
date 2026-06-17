<?php

namespace Tests\Unit;

use App\Infrastructure\Docx\Ooxml\OoxmlNativeDocxParser;
use App\Infrastructure\Docx\Ooxml\OoxmlPackage;
use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use App\Infrastructure\Docx\Ooxml\Writing\OoxmlTextScopeWalker;
use Illuminate\Foundation\Testing\TestCase;
use ZipArchive;

final class OoxmlScopeIndexAlignmentTest extends TestCase
{
    public function test_parser_scope_index_matches_writer_for_bookmark_end_body(): void
    {
        $path = $this->createDocxWithBookmarkEnds();

        try {
            $package = new OoxmlPackage($path);
            $scopes = (new OoxmlTextScopeWalker)->collect($package->document());
            $package->close();

            $parsed = $this->app->make(OoxmlNativeDocxParser::class)->parse($path);
            $target = null;
            foreach ($parsed->blocks as $block) {
                if (($block->textOriginal ?? '') === 'Target paragraph') {
                    $target = $block;

                    break;
                }
            }

            $this->assertNotNull($target);
            $scopeIndex = $target->meta['ooxml_scope_index'] ?? null;
            $this->assertSame(1, $scopeIndex);
            $this->assertSame(
                'Target paragraph',
                trim(OoxmlXml::text($scopes[$scopeIndex]['element'])),
            );
        } finally {
            unlink($path);
        }
    }

    private function createDocxWithBookmarkEnds(): string
    {
        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>First</w:t></w:r></w:p>
    <w:bookmarkEnd w:id="0"/>
    <w:bookmarkEnd w:id="1"/>
    <w:p><w:r><w:t>Target paragraph</w:t></w:r></w:p>
    <w:sectPr/>
  </w:body>
</w:document>
XML;

        $path = sys_get_temp_dir().'/scope-align-'.uniqid('', true).'.docx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->addFromString(
            '[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
        );
        $zip->addFromString(
            'word/_rels/document.xml.rels',
            '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>',
        );
        $zip->close();

        return $path;
    }
}

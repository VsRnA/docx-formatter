<?php

namespace Tests\Unit;

use App\Domain\Document\Port\HtmlSanitizerPort;
use App\Domain\Shared\Port\FileStoragePort;
use App\Infrastructure\Document\EditorHtmlNormalizer;
use App\Infrastructure\Document\PublishedHtmlPreparer;
use Tests\TestCase;

class PublishedHtmlPreparerTest extends TestCase
{
    public function test_inlines_image_after_sanitizer_encodes_equals_in_src(): void
    {
        $key = 'documents/test-doc/uploads/sample.png';
        $storage = $this->createMock(FileStoragePort::class);
        $storage->method('exists')->with($key)->willReturn(true);
        $storage->method('get')->with($key)->willReturn('png-bytes');

        $sanitizer = $this->app->make(HtmlSanitizerPort::class);
        $html = '<figure class="doc-image"><img src="http://localhost/api/v1/mock-storage?key='.rawurlencode($key).'" alt="" /></figure>';
        $sanitized = $sanitizer->sanitize($html);

        $this->assertStringContainsString('&#61;', $sanitized);

        $preparer = new PublishedHtmlPreparer($storage, new EditorHtmlNormalizer);
        $prepared = $preparer->prepareFragment($sanitized);

        $this->assertStringContainsString('data:image/png;base64,', $prepared);
        $this->assertStringNotContainsString('mock-storage', $prepared);
    }

    public function test_repairs_broken_font_quotes_for_pdf(): void
    {
        $storage = $this->createMock(FileStoragePort::class);
        $preparer = new PublishedHtmlPreparer($storage, new EditorHtmlNormalizer);

        $html = '<span style="font-size:12pt; font-family: Arial, &amp;quot;DejaVu Serif&amp;quot;, serif">Text</span>';
        $prepared = $preparer->prepareForPdf($html);

        $this->assertStringContainsString('font-family: DejaVu Sans, Liberation Sans, Arial, sans-serif', $prepared);
        $this->assertStringNotContainsString('&amp;quot;', $prepared);
        $this->assertStringNotContainsString('&quot;DejaVu', $prepared);
    }

    public function test_storage_key_from_encoded_mock_storage_url(): void
    {
        $key = 'documents/abc/uploads/photo.jpg';
        $src = 'http://localhost/api/v1/mock-storage?key&#61;'.rawurlencode($key);

        $this->assertSame($key, EditorHtmlNormalizer::storageKeyFromImageSrc($src));
    }

    public function test_prepare_standalone_uses_document_export_css_and_page_frame(): void
    {
        $storage = $this->createMock(FileStoragePort::class);
        $preparer = new PublishedHtmlPreparer($storage, new EditorHtmlNormalizer);

        $body = '<article class="document-root"><div class="doc-block doc-flow-block"><p>Text</p></div></article>';
        $html = $preparer->prepareStandalone('Manual', $body);

        $this->assertStringContainsString('document-page-frame', $html);
        $this->assertStringContainsString('doc-flow-block', $html);
        $this->assertStringContainsString('.document-page {', $html);
        $this->assertStringContainsString('font-family: \'DejaVu Serif\'', $html);
        $this->assertStringNotContainsString('css/document.css', $html);
        $this->assertSame(1, substr_count($html, '<article class="document-root">'));
    }
}

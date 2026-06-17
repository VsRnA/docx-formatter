<?php

namespace Tests\Unit\Domain;

use App\Domain\Document\Entity\Document;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Document\ValueObject\DocumentMeta;
use App\Domain\Document\ValueObject\DocumentStatus;
use App\Domain\Document\ValueObject\ParseCoverage;
use App\Domain\Document\ValueObject\ProcessingStage;
use PHPUnit\Framework\TestCase;

final class DocumentAggregateTest extends TestCase
{
    public function test_mark_processing_updates_status_and_stage(): void
    {
        $document = $this->makeDocument();

        $document->markProcessing(ProcessingStage::parse());

        $this->assertSame(DocumentStatus::Processing, $document->status());
        $this->assertSame('parse', $document->processingStage());
        $this->assertNull($document->processingError());
    }

    public function test_mark_ready_sets_html_draft_and_completed(): void
    {
        $document = $this->makeDocument();

        $document->markReady('<p>Ready</p>');

        $this->assertSame(DocumentStatus::Ready, $document->status());
        $this->assertSame('completed', $document->processingStage());
        $this->assertSame('<p>Ready</p>', $document->htmlDraft());
    }

    public function test_record_parse_result_stores_coverage_in_meta(): void
    {
        $document = $this->makeDocument();
        $coverage = new ParseCoverage(0.95, 100, 95, true);

        $document->recordParseResult($coverage, [['type' => 'warn']], ['parser' => 'ooxml_native']);

        $meta = $document->meta()->toArray();
        $this->assertSame(0.95, $meta['parse_coverage']['coverage_ratio']);
        $this->assertSame(['parser' => 'ooxml_native'], $meta['parse_meta']);
    }

    public function test_should_translate_respects_meta_flag(): void
    {
        $withTranslation = $this->makeDocument(new DocumentMeta(['translate' => true]));
        $withoutTranslation = $this->makeDocument(new DocumentMeta(['translate' => false]));

        $this->assertTrue($withTranslation->shouldTranslate());
        $this->assertFalse($withoutTranslation->shouldTranslate());
    }

    private function makeDocument(?DocumentMeta $meta = null): Document
    {
        return new Document(
            id: new DocumentId('doc-1'),
            title: 'Test',
            slug: null,
            sourceFileKey: 'documents/doc-1/source.docx',
            languageFrom: 'en',
            languageTo: 'ru',
            status: DocumentStatus::Uploading,
            processingStage: 'queued',
            processingError: null,
            htmlDraft: null,
            htmlPublished: null,
            meta: $meta ?? new DocumentMeta(['translate' => true]),
        );
    }
}

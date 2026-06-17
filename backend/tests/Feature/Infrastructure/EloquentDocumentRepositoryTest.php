<?php

namespace Tests\Feature\Infrastructure;

use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Document\ValueObject\ParseCoverage;
use App\Domain\Document\ValueObject\ProcessingStage;
use App\Enums\DocumentStatus as EloquentDocumentStatus;
use App\Infrastructure\Persistence\Eloquent\Mapper\BlockMapper;
use App\Infrastructure\Persistence\Eloquent\Mapper\DocumentMapper;
use App\Infrastructure\Persistence\Eloquent\Repository\EloquentDocumentRepository;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentDocumentRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_and_save_round_trip(): void
    {
        $model = Document::query()->create([
            'title' => 'Round Trip',
            'source_file_key' => 'documents/test/source.docx',
            'status' => EloquentDocumentStatus::Uploading,
            'processing_stage' => 'queued',
            'meta_json' => ['translate' => false],
        ]);

        $repository = new EloquentDocumentRepository(
            new DocumentMapper(new BlockMapper),
            new BlockMapper,
        );

        $document = $repository->find(new DocumentId($model->id));
        $this->assertSame('Round Trip', $document->title());
        $this->assertFalse($document->shouldTranslate());

        $document->markProcessing(ProcessingStage::parse());
        $document->recordParseResult(
            new ParseCoverage(1.0, 10, 10, true),
            [],
            ['parser' => 'ooxml_native'],
        );
        $repository->save($document);

        $reloaded = $repository->find(new DocumentId($model->id));
        $this->assertSame('parse', $reloaded->processingStage());
        $this->assertSame('ooxml_native', $reloaded->meta()->get('parse_meta')['parser']);
    }
}

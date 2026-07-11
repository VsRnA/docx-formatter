<?php

namespace Tests\Feature\Infrastructure;

use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Document\ValueObject\ParseCoverage;
use App\Domain\Document\ValueObject\ProcessingStage;
use App\Domain\Document\ValueObject\TranslationStatus;
use App\Domain\Docx\ValueObject\BlockType;
use App\Enums\DocumentStatus as EloquentDocumentStatus;
use App\Infrastructure\Persistence\Eloquent\Mapper\BlockMapper;
use App\Infrastructure\Persistence\Eloquent\Mapper\DocumentMapper;
use App\Infrastructure\Persistence\Eloquent\Repository\EloquentDocumentRepository;
use App\Models\Document;
use App\Models\DocumentBlock;
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

    public function test_save_upserts_blocks_without_deleting_existing_rows(): void
    {
        $model = Document::query()->create([
            'title' => 'Upsert Blocks',
            'source_file_key' => 'documents/test/source.docx',
            'status' => EloquentDocumentStatus::Draft,
            'processing_stage' => 'done',
            'meta_json' => [],
        ]);

        $repository = new EloquentDocumentRepository(
            new DocumentMapper(new BlockMapper),
            new BlockMapper,
        );

        $document = $repository->find(new DocumentId($model->id));
        $document->addBlock(new \App\Domain\Document\Entity\DocumentBlock(
            id: '11111111-1111-1111-1111-111111111111',
            type: BlockType::Paragraph,
            sort: 0,
            html: '<p>First</p>',
            textOriginal: 'First',
            textTranslated: null,
            translationStatus: TranslationStatus::Skipped,
            styles: null,
            meta: null,
            assets: null,
            contentJson: null,
        ));
        $repository->save($document);

        $firstBlock = DocumentBlock::query()->first();
        $this->assertNotNull($firstBlock);
        $firstCreatedAt = $firstBlock->created_at;

        $document = $repository->find(new DocumentId($model->id));
        $existingBlock = $document->blocks()[0];
        $document->updateBlock($existingBlock->id, new \App\Domain\Document\Entity\DocumentBlock(
            id: $existingBlock->id,
            type: $existingBlock->type,
            sort: 0,
            html: '<p>Updated</p>',
            textOriginal: 'Updated',
            textTranslated: $existingBlock->textTranslated,
            translationStatus: $existingBlock->translationStatus,
            styles: $existingBlock->styles,
            meta: $existingBlock->meta,
            assets: $existingBlock->assets,
            contentJson: $existingBlock->contentJson,
        ));
        $document->addBlock(new \App\Domain\Document\Entity\DocumentBlock(
            id: '22222222-2222-2222-2222-222222222222',
            type: BlockType::Paragraph,
            sort: 1,
            html: '<p>Second</p>',
            textOriginal: 'Second',
            textTranslated: null,
            translationStatus: TranslationStatus::Skipped,
            styles: null,
            meta: null,
            assets: null,
            contentJson: null,
        ));
        $repository->save($document);

        $this->assertSame(2, DocumentBlock::query()->count());
        $updatedBlock = DocumentBlock::query()->find($existingBlock->id);
        $this->assertNotNull($updatedBlock);
        $this->assertSame('<p>Updated</p>', $updatedBlock->html);
        $this->assertTrue($updatedBlock->created_at->equalTo($firstCreatedAt));
    }

    public function test_save_deletes_all_blocks_when_aggregate_is_empty(): void
    {
        $model = Document::query()->create([
            'title' => 'Delete Last Block',
            'source_file_key' => 'documents/test/source.docx',
            'status' => EloquentDocumentStatus::Draft,
            'processing_stage' => 'done',
            'meta_json' => [],
        ]);

        $repository = new EloquentDocumentRepository(
            new DocumentMapper(new BlockMapper),
            new BlockMapper,
        );

        $document = $repository->find(new DocumentId($model->id));
        $document->addBlock(new \App\Domain\Document\Entity\DocumentBlock(
            id: '33333333-3333-3333-3333-333333333333',
            type: BlockType::Paragraph,
            sort: 0,
            html: '<p>Only</p>',
            textOriginal: 'Only',
            textTranslated: null,
            translationStatus: TranslationStatus::Skipped,
            styles: null,
            meta: null,
            assets: null,
            contentJson: null,
        ));
        $repository->save($document);

        $document = $repository->find(new DocumentId($model->id));
        $document->removeBlock('33333333-3333-3333-3333-333333333333');
        $repository->save($document);

        $this->assertSame(0, DocumentBlock::query()->count());
    }
}

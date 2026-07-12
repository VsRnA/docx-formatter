<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus as EloquentDocumentStatus;
use App\Models\Document;
use App\Models\DocumentBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SaveDocumentDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_draft_creates_block_when_id_is_unknown(): void
    {
        $document = Document::query()->create([
            'title' => 'Draft Save',
            'source_file_key' => 'documents/test/source.docx',
            'status' => EloquentDocumentStatus::Ready,
            'processing_stage' => 'done',
            'meta_json' => [],
        ]);

        $existingBlockId = '11111111-1111-1111-1111-111111111111';
        DocumentBlock::query()->create([
            'id' => $existingBlockId,
            'document_id' => $document->id,
            'type' => 'paragraph',
            'sort' => 0,
            'html' => '<p>Existing</p>',
            'translation_status' => 'skipped',
        ]);

        $newBlockId = '22222222-2222-2222-2222-222222222222';

        $response = $this->putJson("/api/v1/documents/{$document->id}", [
            'blocks' => [
                [
                    'id' => $existingBlockId,
                    'type' => 'paragraph',
                    'sort' => 0,
                    'html' => '<p>Existing updated</p>',
                ],
                [
                    'id' => $newBlockId,
                    'type' => 'paragraph',
                    'sort' => 1,
                    'html' => '<p>Created from orphan</p>',
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertSame(2, DocumentBlock::query()->where('document_id', $document->id)->count());

        $created = DocumentBlock::query()->find($newBlockId);
        $this->assertNotNull($created);
        $this->assertSame('<p>Created from orphan</p>', $created->html);
        $this->assertSame('paragraph', $created->type->value);
    }

    public function test_save_draft_rejects_non_uuid_block_ids(): void
    {
        $document = Document::query()->create([
            'title' => 'Draft Validation',
            'source_file_key' => 'documents/test/source.docx',
            'status' => EloquentDocumentStatus::Ready,
            'processing_stage' => 'done',
            'meta_json' => [],
        ]);

        $response = $this->putJson("/api/v1/documents/{$document->id}", [
            'blocks' => [
                [
                    'id' => 'unknown-367',
                    'type' => 'paragraph',
                    'sort' => 0,
                    'html' => '<p>Invalid id</p>',
                ],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['blocks.0.id']);
    }
}

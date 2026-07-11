<?php

namespace Tests\Unit;

use App\Application\Document\Command\DeleteDocument\DeleteDocumentHandler;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Shared\Port\FileStoragePort;
use App\Enums\DocumentStatus;
use App\Models\Document as DocumentModel;
use App\Models\DocumentResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DeleteDocumentHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_document_and_storage_files(): void
    {
        $document = DocumentModel::query()->create([
            'title' => 'To delete',
            'source_file_key' => 'documents/test/source.docx',
            'status' => DocumentStatus::Draft,
            'meta_json' => ['translated_file_key' => 'documents/test/translated.docx'],
        ]);

        DocumentResource::query()->create([
            'id' => (string) Str::uuid(),
            'document_id' => $document->id,
            'type' => 'image',
            'storage_key' => 'documents/test/image.png',
            'mime_type' => 'image/png',
        ]);

        $storage = $this->createMock(FileStoragePort::class);
        $storage->method('exists')->willReturn(true);
        $storage->expects($this->exactly(3))->method('delete');

        $handler = new DeleteDocumentHandler(
            $this->app->make(DocumentRepositoryInterface::class),
            $storage,
        );

        $handler->execute($document->id);

        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
    }
}

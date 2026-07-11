<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_document_requires_file(): void
    {
        $response = $this->postJson('/api/v1/documents', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    public function test_store_document_rejects_non_docx_extension(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/documents', [
            'file' => UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    public function test_store_document_accepts_docx_file(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/documents', [
            'file' => UploadedFile::fake()->create(
                'instruction.docx',
                100,
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ),
            'translate' => '0',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.title', 'instruction');
    }

    public function test_store_document_accepts_docx_detected_as_zip(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/documents', [
            'file' => UploadedFile::fake()->create(
                'instruction.docx',
                100,
                'application/zip',
            ),
            'translate' => '0',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.title', 'instruction');
    }
}

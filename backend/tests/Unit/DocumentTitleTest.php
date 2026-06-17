<?php

namespace Tests\Unit;

use App\Models\Document;
use App\Support\DocumentTitle;
use Tests\TestCase;

class DocumentTitleTest extends TestCase
{
    public function test_from_uploaded_file_strips_docx_extension(): void
    {
        $this->assertSame('Инструкция по эксплуатации', DocumentTitle::fromUploadedFile('Инструкция по эксплуатации.docx'));
    }

    public function test_display_prefers_original_filename_over_uuid_title(): void
    {
        $document = new Document([
            'title' => '7055a6a5-39e0-4fd0-abdb-905b4b1206ed',
            'meta_json' => [
                'original_filename' => 'Manual EN.docx',
            ],
        ]);

        $this->assertSame('Manual EN', DocumentTitle::display($document));
    }

    public function test_display_keeps_user_friendly_title(): void
    {
        $document = new Document([
            'title' => 'Инструкция',
            'meta_json' => [
                'original_filename' => 'Manual EN.docx',
            ],
        ]);

        $this->assertSame('Инструкция', DocumentTitle::display($document));
    }
}

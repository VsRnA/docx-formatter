<?php

namespace Tests\Unit;

use App\Models\Document;
use App\Support\DocumentDocxKeyResolver;
use PHPUnit\Framework\TestCase;

final class DocumentDocxKeyResolverTest extends TestCase
{
    public function test_prefers_working_file_key_over_source(): void
    {
        $document = new Document([
            'source_file_key' => 'documents/1/source.docx',
            'meta_json' => ['working_file_key' => 'documents/1/working.docx'],
        ]);

        $resolver = new DocumentDocxKeyResolver;

        $this->assertSame('documents/1/working.docx', $resolver->activeKey($document));
    }

    public function test_falls_back_to_translated_then_source(): void
    {
        $document = new Document([
            'source_file_key' => 'documents/1/source.docx',
            'meta_json' => ['translated_file_key' => 'documents/1/translated.docx'],
        ]);

        $resolver = new DocumentDocxKeyResolver;

        $this->assertSame('documents/1/translated.docx', $resolver->activeKey($document));
    }
}

<?php

namespace App\Support;

use App\Models\Document;

final class DocumentDocxKeyResolver
{
    public function activeKey(Document $document): string
    {
        $meta = is_array($document->meta_json) ? $document->meta_json : [];
        $working = $meta['working_file_key'] ?? null;

        if (is_string($working) && $working !== '') {
            return $working;
        }

        $translated = $meta['translated_file_key'] ?? null;
        if (is_string($translated) && $translated !== '') {
            return $translated;
        }

        return $document->source_file_key;
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResourceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_id' => $this->document_id,
            'type' => $this->type?->value ?? $this->type,
            'storage_key' => $this->storage_key,
            'url' => $this->url,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'meta_json' => $this->meta_json,
        ];
    }
}

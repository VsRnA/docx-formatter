<?php

namespace App\Models;

use App\Enums\ResourceType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentResource extends Model
{
    use HasUuids;

    protected $fillable = [
        'document_id',
        'type',
        'storage_key',
        'url',
        'mime_type',
        'size',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'type' => ResourceType::class,
            'meta_json' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}

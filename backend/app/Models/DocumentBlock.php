<?php

namespace App\Models;

use App\Enums\BlockType;
use App\Enums\TranslationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentBlock extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'document_id',
        'type',
        'sort',
        'html',
        'content_json',
        'text_original',
        'text_translated',
        'translation_status',
        'styles_json',
        'meta_json',
        'assets_json',
    ];

    protected function casts(): array
    {
        return [
            'type' => BlockType::class,
            'translation_status' => TranslationStatus::class,
            'content_json' => 'array',
            'styles_json' => 'array',
            'meta_json' => 'array',
            'assets_json' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}

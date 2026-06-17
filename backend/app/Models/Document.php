<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use HasUuids;

    protected $fillable = [
        'title',
        'slug',
        'source_file_key',
        'language_from',
        'language_to',
        'status',
        'processing_error',
        'processing_stage',
        'html_draft',
        'html_published',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'meta_json' => 'array',
        ];
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(DocumentBlock::class)->orderBy('sort');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(DocumentResource::class);
    }
}

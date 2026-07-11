<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRevision extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'trigger',
        'label',
        'blocks_snapshot',
        'html_draft_snapshot',
        'created_at',
    ];

    protected $casts = [
        'blocks_snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}

<?php

namespace App\Http\Resources;

use App\Models\DocumentRevision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DocumentRevision */
class DocumentRevisionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $blocks = is_array($this->blocks_snapshot) ? $this->blocks_snapshot : [];
        $firstHeading = collect($blocks)->first(
            fn (mixed $block) => is_array($block) && ($block['type'] ?? null) === 'heading',
        );

        return [
            'id' => $this->id,
            'document_id' => $this->document_id,
            'trigger' => $this->trigger,
            'label' => $this->label,
            'blocks_count' => count($blocks),
            'summary' => is_array($firstHeading)
                ? strip_tags((string) ($firstHeading['html'] ?? ''))
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'blocks_snapshot' => $this->when(
                $request->route()?->parameter('revision') !== null,
                $blocks,
            ),
            'html_draft_snapshot' => $this->when(
                $request->route()?->parameter('revision') !== null,
                $this->html_draft_snapshot,
            ),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Support\DocumentTitle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => DocumentTitle::display($this->resource),
            'slug' => $this->slug,
            'status' => $this->status?->value ?? $this->status,
            'processing_stage' => $this->processing_stage,
            'processing_error' => $this->processing_error,
            'language_from' => $this->language_from,
            'language_to' => $this->language_to,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'revisions_count' => $this->revisions_count ?? 0,
            'layout' => $this->extractLayout(),
        ];
    }

    /**
     * @return array{section?: array<string, mixed>, defaults?: array<string, mixed>}|null
     */
    private function extractLayout(): ?array
    {
        $meta = is_array($this->meta_json) ? $this->meta_json : [];
        $parseMeta = $meta['parse_meta'] ?? null;
        if (! is_array($parseMeta)) {
            return null;
        }

        $section = $parseMeta['section'] ?? null;
        $defaults = $parseMeta['defaults'] ?? null;

        if (! is_array($section) && ! is_array($defaults)) {
            return null;
        }

        return array_filter([
            'section' => is_array($section) ? $section : null,
            'defaults' => is_array($defaults) ? $defaults : null,
        ], static fn (mixed $value): bool => $value !== null);
    }
}

<?php

namespace App\Http\Resources;

use App\Infrastructure\Document\BlockHtmlWrapper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentBlockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $assets = $this->normalizeStorageUrls($this->assets_json);

        return [
            'id' => $this->id,
            'document_id' => $this->document_id,
            'type' => $this->type?->value ?? $this->type,
            'sort' => $this->sort,
            'html' => $this->normalizeStorageUrls(
                BlockHtmlWrapper::sanitizeBlockInnerHtml((string) ($this->html ?? '')),
            ),
            'content_json' => $this->content_json,
            'text_original' => $this->text_original,
            'text_translated' => $this->text_translated,
            'translation_status' => $this->translation_status?->value ?? $this->translation_status,
            'styles_json' => $this->styles_json,
            'meta_json' => $this->meta_json,
            'assets_json' => $assets,
        ];
    }

    private function normalizeStorageUrls(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $publicBase = rtrim((string) config('app.url'), '/');

        return preg_replace(
            '#https?://backend(?::\d+)?(/api/v1/mock-storage\?key=[^"\'\s<>]+)#',
            $publicBase.'$1',
            $value,
        ) ?? $value;
    }
}

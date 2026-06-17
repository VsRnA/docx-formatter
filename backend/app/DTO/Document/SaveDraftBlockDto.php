<?php

namespace App\DTO\Document;

final readonly class SaveDraftBlockDto
{
    /**
     * @param  array<string, mixed>|null  $styles
     * @param  array<string, mixed>|null  $meta
     * @param  array<string, mixed>|null  $assets
     */
    public function __construct(
        public string $id,
        public string $type,
        public int $sort,
        public ?string $html,
        public ?array $styles = null,
        public ?array $meta = null,
        public ?array $assets = null,
    ) {}
}

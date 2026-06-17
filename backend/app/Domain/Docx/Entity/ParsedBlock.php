<?php

namespace App\Domain\Docx\Entity;

use App\Domain\Docx\ValueObject\BlockType;

final readonly class ParsedBlock
{
    /**
     * @param  array<string, mixed>|null  $styles
     * @param  array<string, mixed>|null  $meta
     * @param  array<string, mixed>|null  $assets
     * @param  array<string, mixed>|null  $contentJson
     */
    public function __construct(
        public BlockType $type,
        public int $sort,
        public ?string $html,
        public ?string $textOriginal,
        public ?array $styles = null,
        public ?array $meta = null,
        public ?array $assets = null,
        public ?string $localImagePath = null,
        public ?array $contentJson = null,
    ) {}
}

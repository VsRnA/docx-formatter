<?php

namespace App\Domain\Docx\Entity;

final readonly class ParsedDocument
{
    /**
     * @param  list<ParsedBlock>  $blocks
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        public string $title,
        public array $blocks,
        public ?array $meta = null,
    ) {}
}

<?php

namespace App\Domain\Docx\ValueObject;

final class ParseContext
{
    /** @var list<array{type: string, message: string}> */
    public array $warnings = [];

    public function __construct(
        public int $sort = 0,
        public int $ooxmlScopeIndex = 0,
        public int $inlineColumnOffsetPx = 0,
    ) {}

    public function nextSort(): int
    {
        return $this->sort++;
    }

    public function nextOoxmlScopeIndex(): int
    {
        return $this->ooxmlScopeIndex++;
    }

    public function warn(string $type, string $message): void
    {
        $this->warnings[] = ['type' => $type, 'message' => $message];
    }
}

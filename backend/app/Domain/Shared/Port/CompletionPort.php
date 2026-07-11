<?php

namespace App\Domain\Shared\Port;

interface CompletionPort
{
    public function complete(
        string $systemPrompt,
        string $userPrompt,
        int $maxTokens = 4000,
        float $temperature = 0.2,
    ): string;
}

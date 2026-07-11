<?php

namespace App\Application\Ai\Command\ReworkText;

use App\Domain\Shared\Port\CompletionPort;
use RuntimeException;

final class ReworkTextHandler
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You rewrite document text according to user instructions.
Return only the rewritten text without quotes, markdown fences, or commentary.
Preserve the original language unless the user explicitly asks to translate.
PROMPT;

    public function __construct(
        private readonly CompletionPort $client,
    ) {}

    public function execute(string $text, string $prompt): string
    {
        $text = trim($text);
        $prompt = trim($prompt);

        if ($text === '') {
            throw new RuntimeException('Text is empty');
        }

        if ($prompt === '') {
            throw new RuntimeException('Prompt is empty');
        }

        $userPrompt = "Instruction:\n{$prompt}\n\nText:\n{$text}";

        $response = trim($this->client->complete(self::SYSTEM_PROMPT, $userPrompt, 4000, 0.3));

        if ($response === '') {
            throw new RuntimeException('YandexGPT returned an empty response');
        }

        return $response;
    }
}

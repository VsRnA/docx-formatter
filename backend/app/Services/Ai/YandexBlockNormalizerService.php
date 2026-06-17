<?php

namespace App\Services\Ai;

use App\Domain\Docx\Port\BlockNormalizerPort;

final class YandexBlockNormalizerService implements BlockNormalizerPort
{
    private const SYSTEM_PROMPT = 'You normalize OOXML fragments into structured document blocks. '
        .'Return ONLY valid JSON with this schema: '
        .'{"kind":"paragraph|heading|list|caption","children":[{"kind":"text","text":"..."}]}. '
        .'Extract readable text; do not invent content. Use "paragraph" when unsure.';

    public function __construct(
        private readonly YandexCompletionClient $client,
    ) {}

    public function normalize(string $ooxmlFragment, ?string $plainText = null): ?array
    {
        $fragment = trim($ooxmlFragment);
        if ($fragment === '' && ($plainText === null || trim($plainText) === '')) {
            return null;
        }

        $user = "OOXML fragment:\n".$fragment;
        if ($plainText !== null && trim($plainText) !== '') {
            $user .= "\n\nPlain text hint:\n".trim($plainText);
        }

        $response = $this->client->complete(self::SYSTEM_PROMPT, $user, 2000, 0.1);
        $decoded = $this->decodeJson($response);

        return $this->validatePayload($decoded);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(string $response): ?array
    {
        $json = $response;
        if (preg_match('/\{.*\}/s', $response, $matches) === 1) {
            $json = $matches[0];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array{kind: string, children: list<array{kind: string, text: string}>}|null
     */
    private function validatePayload(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        $kind = (string) ($payload['kind'] ?? '');
        if (! in_array($kind, ['paragraph', 'heading', 'list', 'caption'], true)) {
            return null;
        }

        $children = $payload['children'] ?? [];
        if (! is_array($children) || $children === []) {
            return null;
        }

        $normalizedChildren = [];
        foreach ($children as $child) {
            if (! is_array($child) || ($child['kind'] ?? '') !== 'text') {
                continue;
            }

            $text = trim((string) ($child['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $normalizedChildren[] = ['kind' => 'text', 'text' => $text];
        }

        if ($normalizedChildren === []) {
            return null;
        }

        return ['kind' => $kind, 'children' => $normalizedChildren];
    }
}

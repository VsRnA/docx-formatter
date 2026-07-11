<?php

namespace App\Infrastructure\External\Ai;

use App\Domain\Docx\Port\BlockNormalizerPort;

final class MockBlockNormalizerService implements BlockNormalizerPort
{
    public function normalize(string $ooxmlFragment, ?string $plainText = null): ?array
    {
        $text = trim((string) ($plainText ?? ''));
        if ($text === '') {
            return null;
        }

        return [
            'kind' => 'paragraph',
            'children' => [
                ['kind' => 'text', 'text' => $text],
            ],
        ];
    }
}

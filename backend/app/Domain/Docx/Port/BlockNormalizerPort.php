<?php

namespace App\Domain\Docx\Port;

interface BlockNormalizerPort
{
    /**
     * @return array{kind: string, children: list<array{kind: string, text: string}>}|null
     */
    public function normalize(string $ooxmlFragment, ?string $plainText = null): ?array;
}

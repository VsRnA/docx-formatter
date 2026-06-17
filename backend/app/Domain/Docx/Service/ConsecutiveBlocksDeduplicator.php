<?php

namespace App\Domain\Docx\Service;

use App\Domain\Docx\Entity\ParsedBlock;
use App\Domain\Docx\Service\Support\TextRunFragmentMerger;
use App\Domain\Docx\ValueObject\BlockType;

final class ConsecutiveBlocksDeduplicator
{
    public function __construct(
        private readonly TextRunFragmentMerger $merger,
    ) {}

    /**
     * @param  list<ParsedBlock>  $blocks
     * @return list<ParsedBlock>
     */
    public function deduplicate(array $blocks): array
    {
        $result = [];
        $previousPlain = null;

        foreach ($blocks as $block) {
            $plain = $this->merger->normalize($this->plainText($block));
            if ($plain !== '' && $plain === $previousPlain) {
                $this->replaceWithRicherBlock($result, $block);

                continue;
            }

            $result[] = $block;
            $previousPlain = $plain !== '' ? $plain : $previousPlain;
        }

        return $result;
    }

    private function plainText(ParsedBlock $block): string
    {
        $plain = trim((string) ($block->textOriginal ?? ''));
        if ($plain !== '') {
            return $plain;
        }

        return trim(strip_tags((string) ($block->html ?? '')));
    }

    /**
     * @param  list<ParsedBlock>  $result
     */
    private function replaceWithRicherBlock(array &$result, ParsedBlock $candidate): void
    {
        if ($result === []) {
            return;
        }

        $index = array_key_last($result);
        $existing = $result[$index];

        if (strlen((string) $candidate->html) > strlen((string) $existing->html)) {
            $result[$index] = $candidate;
        }
    }
}

<?php

namespace App\Domain\Docx\Service;

use App\Domain\Docx\Entity\ParsedBlock;
use App\Domain\Docx\Entity\ParsedDocument;
use App\Domain\Docx\ValueObject\BlockType;
use App\Domain\Docx\ValueObject\ParseContext;

final class DocumentAssembler
{
    public function __construct(
        private readonly ListBlocksGrouper $listGrouper,
        private readonly ConsecutiveBlocksDeduplicator $deduplicator,
        private readonly AnchoredCalloutBlockMerger $calloutMerger,
        private readonly FigureGalleryCaptionMerger $figureGalleryMerger,
    ) {}

    /**
     * @param  list<ParsedBlock>  $blocks
     */
    public function assemble(string $fallbackTitle, array $blocks, ParseContext $context): ParsedDocument
    {
        $blocks = $this->calloutMerger->merge($blocks);
        $blocks = $this->figureGalleryMerger->merge($blocks);
        $blocks = $this->deduplicator->deduplicate($blocks);
        $blocks = $this->listGrouper->group($blocks);
        $title = $fallbackTitle;

        if ($blocks !== [] && $blocks[0]->type === BlockType::Heading) {
            $title = strip_tags($blocks[0]->html ?? $blocks[0]->textOriginal ?? $title);
        }

        return new ParsedDocument(
            title: $title,
            blocks: $blocks,
            meta: [
                'parser' => 'ooxml_native',
                'block_count' => count($blocks),
                'warnings' => $context->warnings,
            ],
        );
    }
}

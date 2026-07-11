<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing\Paragraph;

use App\Domain\Docx\Entity\ParsedBlock;
use App\Domain\Docx\ValueObject\BlockType;
use App\Domain\Docx\ValueObject\ParseContext;
use App\Infrastructure\Docx\Ooxml\Parsing\Layout\ParagraphLayoutHelper;
use DOMElement;

final class ParagraphBlockSplitter
{
    public function __construct(
        private readonly ParagraphLayoutHelper $layout,
        private readonly ParagraphBlockFactory $blocks,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $pendingImages
     * @return list<ParsedBlock>
     */
    public function split(
        ParseContext $context,
        DOMElement $paragraph,
        string $tag,
        BlockType $type,
        string $attrString,
        string $innerHtml,
        string $plain,
        ?string $styleId,
        ?string $numId,
        ?string $ilvl,
        bool $isList,
        ?array $stylesJson,
        array $pendingImages,
        bool $pageBreakBefore,
        int $ooxmlScopeIndex,
        callable $wrapAnchoredCanvas,
        callable $pendingImagesInHtml,
    ): array {
        $innerHtml = $wrapAnchoredCanvas($innerHtml, $pendingImages);
        $segments = $this->layout->splitOnPageBreakMarkers($innerHtml);

        if (count($segments) > 1) {
            $blocks = [];
            foreach ($segments as $index => $segmentHtml) {
                if (trim(strip_tags($segmentHtml)) === '' && ! str_contains($segmentHtml, '<figure')) {
                    continue;
                }

                $blocks = array_merge(
                    $blocks,
                    $this->split(
                        $context,
                        $paragraph,
                        $tag,
                        $type,
                        $attrString,
                        $segmentHtml,
                        trim(strip_tags($segmentHtml)),
                        $styleId,
                        $numId,
                        $ilvl,
                        $isList,
                        $stylesJson,
                        $pendingImagesInHtml($segmentHtml, $pendingImages),
                        $pageBreakBefore || $index > 0,
                        $ooxmlScopeIndex,
                        $wrapAnchoredCanvas,
                        $pendingImagesInHtml,
                    ),
                );
            }

            return $blocks !== [] ? $blocks : [];
        }

        return [
            $this->blocks->make(
                $context,
                $paragraph,
                $tag,
                $type,
                $attrString,
                $innerHtml,
                $plain,
                $styleId,
                $numId,
                $ilvl,
                $isList,
                $stylesJson,
                $pendingImages,
                $pageBreakBefore,
                $ooxmlScopeIndex,
            ),
        ];
    }
}

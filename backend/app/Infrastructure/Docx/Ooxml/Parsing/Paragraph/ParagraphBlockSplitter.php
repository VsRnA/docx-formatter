<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing\Paragraph;

use App\Domain\Docx\Entity\ParsedBlock;
use App\Domain\Docx\ValueObject\BlockType;
use App\Infrastructure\Docx\Ooxml\Parsing\Layout\ParagraphLayoutHelper;
use App\Domain\Docx\ValueObject\ParseContext;
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
        $innerHtml = $this->layout->wrapPageOverlay($innerHtml);
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

        $symbolRows = $this->layout->extractSymbolRows($innerHtml);
        if ($this->layout->isAnchoredDiagram($innerHtml)) {
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

        if (count($symbolRows) <= 1) {
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

        $tailHtml = $this->layout->extractNonSymbolTail($innerHtml);
        $result = [];

        foreach ($symbolRows as $index => $rowHtml) {
            if (trim(strip_tags($rowHtml)) === '' && ! str_contains($rowHtml, '<figure')) {
                continue;
            }

            $rowAttrString = $attrString !== '' ? $attrString : ' class="doc-paragraph--symbols"';

            $result[] = $this->blocks->make(
                $context,
                $paragraph,
                $tag,
                $type,
                $rowAttrString,
                $rowHtml,
                trim(strip_tags($rowHtml)),
                $styleId,
                $numId,
                $ilvl,
                $isList,
                $stylesJson,
                $pendingImagesInHtml($rowHtml, $pendingImages),
                $pageBreakBefore || $index > 0,
                $ooxmlScopeIndex,
            );
        }

        if ($tailHtml !== '') {
            $result[] = $this->blocks->make(
                $context,
                $paragraph,
                $tag,
                $type,
                $attrString,
                $tailHtml,
                trim(strip_tags($tailHtml)),
                $styleId,
                $numId,
                $ilvl,
                $isList,
                $stylesJson,
                $pendingImagesInHtml($tailHtml, $pendingImages),
                $pageBreakBefore,
                $ooxmlScopeIndex,
            );
        }

        return $result !== [] ? $result : [
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

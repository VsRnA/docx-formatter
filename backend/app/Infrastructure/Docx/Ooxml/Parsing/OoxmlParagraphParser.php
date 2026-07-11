<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing;

use App\Domain\Docx\Entity\ParsedBlock;
use App\Domain\Docx\ValueObject\BlockType;
use App\Domain\Docx\ValueObject\ParseContext;
use App\Infrastructure\Docx\Ooxml\OoxmlPackage;
use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use App\Infrastructure\Docx\Ooxml\Parsing\Layout\ParagraphLayoutHelper;
use App\Infrastructure\Docx\Ooxml\Parsing\Paragraph\ParagraphBlockSplitter;
use App\Infrastructure\Docx\Ooxml\Styles\OoxmlNumberingResolver;
use App\Infrastructure\Docx\Ooxml\Styles\OoxmlStyleResolver;
use DOMElement;

final class OoxmlParagraphParser
{
    public function __construct(
        private readonly OoxmlStyleResolver $styles,
        private readonly OoxmlNumberingResolver $numbering,
        private readonly OoxmlRunParser $runs,
        private readonly OoxmlImageBlockFactory $images,
        private readonly ParagraphBlockSplitter $blockSplitter,
        private readonly ParagraphLayoutHelper $layout,
    ) {}

    /**
     * @return list<ParsedBlock>
     */
    public function parse(DOMElement $paragraph, OoxmlPackage $package, ParseContext $context, int $ooxmlScopeIndex): array
    {
        $pendingImages = [];
        $pPr = OoxmlXml::child($paragraph, 'pPr');
        $styleId = null;
        $numId = null;
        $ilvl = null;

        if ($pPr) {
            $pStyle = OoxmlXml::child($pPr, 'pStyle');
            $styleId = $pStyle ? OoxmlXml::attr($pStyle, 'val') : null;
            $numPr = OoxmlXml::child($pPr, 'numPr');
            if ($numPr) {
                $numIdNode = OoxmlXml::child($numPr, 'numId');
                $ilvlNode = OoxmlXml::child($numPr, 'ilvl');
                $numId = $numIdNode ? OoxmlXml::attr($numIdNode, 'val') : null;
                $ilvl = $ilvlNode ? OoxmlXml::attr($ilvlNode, 'val') : null;
            }
        }

        $pageBreakBefore = $pPr && OoxmlXml::onOff($pPr, 'pageBreakBefore');

        $context->inlineColumnOffsetPx = 0;
        $parsed = $this->runs->parseContainer($paragraph, $styleId, $package, $pendingImages, $context);
        $plain = $parsed['plain'];
        $innerHtml = $parsed['html'];
        $innerHtml = $this->layout->applyFlowingImageLayout($innerHtml, $pendingImages, $plain);

        if ($plain === '' && $pendingImages === [] && trim(strip_tags($innerHtml)) === '') {
            return [];
        }

        if ($this->layout->shouldCreateStandaloneImageBlock($plain, $pendingImages, $innerHtml)) {
            $pending = $pendingImages[0];
            $attributes = is_array($pending['attributes'] ?? null) ? $pending['attributes'] : [];
            $attributes = $this->layout->normalizeAttributesForFlow($attributes);

            return [
                new ParsedBlock(
                    type: BlockType::Image,
                    sort: $context->nextSort(),
                    html: $this->images->buildPendingFigure($attributes, (string) $pending['marker']),
                    textOriginal: null,
                    meta: array_filter([
                        'source' => 'ooxml_drawing',
                        'ooxml_scope_index' => $ooxmlScopeIndex,
                        'image' => $attributes,
                    ]),
                    assets: ['relationship_id' => $pending['relationship_id'] ?? $pending['marker']],
                    localImagePath: $pending['local_path'] ?? null,
                ),
            ];
        }

        $headingLevel = $this->styles->headingLevel($styleId);
        $isList = $numId !== null && $numId !== '0';
        $tag = $isList ? 'li' : ($headingLevel ? 'h'.$headingLevel : 'p');
        $type = $isList ? BlockType::List : ($headingLevel ? BlockType::Heading : BlockType::Paragraph);

        $cssRules = $this->styles->paragraphCss($pPr, $styleId);
        if ($isList) {
            $numberingPPr = $this->numbering->levelParagraphProperties($numId, $ilvl);
            if ($numberingPPr) {
                $cssRules = $this->layout->mergeCssRules($cssRules, $this->styles->paragraphCss($numberingPPr, null));
            }
        }

        $classNames = $this->layout->paragraphClasses($pendingImages, $plain, $innerHtml);

        if ($tag === 'p' && $this->layout->requiresDivWrapper($innerHtml)) {
            $tag = 'div';
        }

        $attrs = [];
        if ($cssRules !== []) {
            $attrs[] = 'style="'.implode('; ', $cssRules).'"';
        }
        if ($classNames !== []) {
            $attrs[] = 'class="'.implode(' ', $classNames).'"';
        }

        $stylesJson = $parsed['inline'] !== [] ? ['inline' => $parsed['inline']] : null;
        if ($cssRules !== []) {
            $stylesJson = array_merge($stylesJson ?? [], ['paragraph' => ['style_id' => $styleId]]);
        }

        $attrString = $attrs !== [] ? ' '.implode(' ', $attrs) : '';

        return $this->blockSplitter->split(
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
            static fn (string $html, array $images): string => $html,
            fn (string $html, array $images): array => $this->pendingImagesInHtml($html, $images),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $pendingImages
     * @return list<array<string, mixed>>
     */
    private function pendingImagesInHtml(string $html, array $pendingImages): array
    {
        return array_values(array_filter(
            $pendingImages,
            static function (array $pending) use ($html): bool {
                $marker = (string) ($pending['marker'] ?? $pending['relationship_id'] ?? '');
                if ($marker === '') {
                    return false;
                }

                return str_contains($html, 'data-pending-marker="'.$marker.'"');
            },
        ));
    }
}

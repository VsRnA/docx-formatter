<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing;

use App\Infrastructure\Docx\Ooxml\OoxmlPackage;
use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use App\Infrastructure\Docx\Ooxml\Parsing\Layout\SymbolRowLayout;
use App\Infrastructure\Docx\Ooxml\Parsing\Run\OoxmlAlternateContentRenderer;
use App\Infrastructure\Docx\Ooxml\Parsing\Run\OoxmlMathRenderer;
use App\Infrastructure\Docx\Ooxml\Parsing\Run\OoxmlRunFragmentAccumulator;
use App\Infrastructure\Docx\Ooxml\Parsing\Run\OoxmlRunTextFormatter;
use App\Infrastructure\Document\BlockHtmlWrapper;
use App\Domain\Docx\Service\Support\TextRunFragmentMerger;
use App\Domain\Docx\ValueObject\ParseContext;
use App\Support\Constants\OoxmlTags;
use DOMElement;

final class OoxmlRunParser
{
    public function __construct(
        private readonly TextRunFragmentMerger $merger,
        private readonly OoxmlRunTextFormatter $textFormatter,
        private readonly OoxmlAlternateContentRenderer $alternateContent,
        private readonly SymbolRowLayout $symbolRows,
        private readonly OoxmlMathRenderer $math,
    ) {}

    /**
     * @param  list<array{marker: string, relationship_id: string, local_path: ?string, attributes: array<string, mixed>}>|null  $pendingImages
     * @return array{html: string, plain: string, inline: array<string, mixed>}
     */
    public function parseContainer(
        DOMElement $container,
        ?string $paragraphStyleId = null,
        ?OoxmlPackage $package = null,
        ?array &$pendingImages = null,
        ?ParseContext $context = null,
    ): array {
        $inline = [];
        $accumulator = new OoxmlRunFragmentAccumulator($this->merger, $this->textFormatter);
        $this->walkContainer($container, $paragraphStyleId, $package, $pendingImages, $context, $accumulator, $inline);

        $html = implode('', $this->symbolRows->consolidate($accumulator->parts()));
        $htmlForPlain = BlockHtmlWrapper::stripUnsupportedFigures(
            preg_replace(
                '/<(?:span|div)[^>]*data-doc-formula="1"[^>]*>.*?<\/(?:span|div)>/su',
                '',
                $html,
            ) ?? $html,
        );
        $plain = trim(str_replace("\u{00A0}", ' ', strip_tags(str_replace('<br>', ' ', $htmlForPlain))));
        $plain = $this->merger->dedupeDoubledText($plain);

        return [
            'html' => $html,
            'plain' => $plain,
            'inline' => $inline,
        ];
    }

    /**
     * @param  list<array{marker: string, relationship_id: string, local_path: ?string, attributes: array<string, mixed>}>|null  $pendingImages
     * @param  array<string, mixed>  $inline
     */
    private function walkContainer(
        DOMElement $container,
        ?string $paragraphStyleId,
        ?OoxmlPackage $package,
        ?array &$pendingImages,
        ?ParseContext $context,
        OoxmlRunFragmentAccumulator $accumulator,
        array &$inline,
    ): void {
        foreach ($container->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if ($this->shouldSkipContainerChild($child->localName)) {
                continue;
            }

            if ($child->localName === OoxmlTags::DELETE) {
                continue;
            }

            if ($child->localName === OoxmlTags::INSERT) {
                $this->walkContainer($child, $paragraphStyleId, $package, $pendingImages, $context, $accumulator, $inline);

                continue;
            }

            if ($this->math->isMathElement($child)) {
                $accumulator->appendHtml($this->math->renderInline($child));

                continue;
            }

            if (in_array($child->localName, ['r', 'hyperlink'], true)) {
                $accumulator->appendRunFragment(
                    $this->parseRun($child, $paragraphStyleId, $package, $pendingImages, $context),
                    $paragraphStyleId,
                    $inline,
                );

                continue;
            }

            if (in_array($child->localName, ['smartTag', 'fldSimple'], true)) {
                $this->walkContainer($child, $paragraphStyleId, $package, $pendingImages, $context, $accumulator, $inline);

                continue;
            }

            if ($child->localName === 'sdt') {
                $content = OoxmlXml::child($child, OoxmlTags::SDT_CONTENT);
                if ($content) {
                    $this->walkContainer($content, $paragraphStyleId, $package, $pendingImages, $context, $accumulator, $inline);
                }

                continue;
            }

            if ($context !== null) {
                $context->warn(
                    'unhandled_container_child',
                    sprintf('Unhandled OOXML container child <%s>', $child->localName),
                );
            }
        }
    }

  /**
     * @param  list<array{marker: string, relationship_id: string, local_path: ?string, attributes: array<string, mixed>}>|null  $pendingImages
     * @return array{html: string, plain: string, inline: array<string, mixed>, run: DOMElement}
     */
    private function parseRun(
        DOMElement $run,
        ?string $paragraphStyleId,
        ?OoxmlPackage $package = null,
        ?array &$pendingImages = null,
        ?ParseContext $context = null,
    ): array {
        if ($run->localName === 'hyperlink') {
            $href = OoxmlXml::attr($run, 'anchor') ?? OoxmlXml::attr($run, 'id') ?? '#';
            $inner = $this->parseContainer($run, $paragraphStyleId, $package, $pendingImages, $context);
            if ($inner['plain'] === '' && $inner['html'] === '') {
                return ['html' => '', 'plain' => '', 'inline' => [], 'run' => $run];
            }

            return [
                'html' => '<a href="'.e($href).'">'.$inner['html'].'</a>',
                'plain' => $inner['plain'],
                'inline' => $inner['inline'],
                'run' => $run,
            ];
        }

        $plainParts = [];
        $htmlParts = [];
        $inline = [];
        $lastTextNode = '';
        $lastRPr = OoxmlXml::serializeRunProperties(OoxmlXml::child($run, 'rPr'));
        $hasDrawing = false;
        $hasPict = false;

        foreach ($run->childNodes as $child) {
            if ($child instanceof DOMElement) {
                if ($child->localName === 'drawing') {
                    $hasDrawing = true;
                }

                if ($child->localName === 'pict') {
                    $hasPict = true;
                }
            }
        }

        $skipPictTextboxes = $hasDrawing && $hasPict;
        $parseParagraph = function (DOMElement $paragraph) use ($paragraphStyleId, $package, &$pendingImages, $context): array {
            $parsed = $this->parseContainer($paragraph, $paragraphStyleId, $package, $pendingImages, $context);

            return ['html' => $parsed['html'], 'plain' => $parsed['plain']];
        };

        foreach ($run->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if (in_array($child->localName, ['fldChar', 'instrText', 'delInstrText'], true)) {
                continue;
            }

            if ($this->math->isMathElement($child)) {
                $htmlParts[] = $this->math->renderInline($child);

                continue;
            }

            if ($child->localName === 'AlternateContent') {
                if ($package !== null && $pendingImages !== null) {
                    $alternateHtml = $this->alternateContent->renderAlternateContent(
                        $child,
                        $parseParagraph,
                        $paragraphStyleId,
                        $package,
                        $pendingImages,
                        $context,
                    );
                    if ($alternateHtml !== '') {
                        $htmlParts[] = $alternateHtml;
                        $plainParts[] = trim(strip_tags($alternateHtml));
                    }
                }

                continue;
            }

            if (in_array($child->localName, ['drawing', 'pict'], true)) {
                if ($package !== null && $pendingImages !== null) {
                    $includeTextboxes = ! ($child->localName === 'pict' && $skipPictTextboxes);
                    $graphicHtml = $this->alternateContent->renderGraphicScope(
                        $child,
                        $parseParagraph,
                        $paragraphStyleId,
                        $package,
                        $pendingImages,
                        $context,
                        $includeTextboxes,
                    );
                    if ($graphicHtml !== '') {
                        $htmlParts[] = $graphicHtml;
                        $plainParts[] = trim(strip_tags($graphicHtml));
                    }
                }

                continue;
            }

            if ($child->localName === 't') {
                $text = $child->textContent ?? '';
                if ($child->hasAttributeNS('http://www.w3.org/XML/1998/namespace', 'space')
                    && $child->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'space') === 'preserve') {
                    $text = str_replace("\u{00A0}", ' ', $text);
                }
                if ($text === '') {
                    continue;
                }

                $rPr = OoxmlXml::child($run, 'rPr');
                $serializedRPr = OoxmlXml::serializeRunProperties($rPr);
                if ($this->merger->repeatsPrevious($lastTextNode, $text) && $serializedRPr === $lastRPr) {
                    $lastIndex = array_key_last($htmlParts);
                    if ($lastIndex !== null) {
                        $htmlParts[$lastIndex] = $this->merger->prefersRicherHtml(
                            (string) $htmlParts[$lastIndex],
                            $this->textFormatter->formatText($text, $rPr, $paragraphStyleId, $inline),
                        );
                    }

                    continue;
                }

                $plainParts[] = $text;
                $htmlParts[] = $this->textFormatter->formatText($text, $rPr, $paragraphStyleId, $inline);
                $lastTextNode = $text;
                $lastRPr = $serializedRPr;

                continue;
            }

            if ($child->localName === 'tab') {
                $plainParts[] = "\u{2003}";
                $htmlParts[] = "\u{2003}";

                continue;
            }

            if ($child->localName === 'br') {
                $type = OoxmlXml::attr($child, 'type');
                $htmlParts[] = $type === 'page'
                    ? '<span data-doc-page-break="1"></span>'
                    : '<br>';

                continue;
            }

            if ($child->localName === 'cr') {
                $htmlParts[] = '<br>';

                continue;
            }

            if ($child->localName === 'noBreakHyphen') {
                $plainParts[] = "\u{2011}";
                $htmlParts[] = "\u{2011}";

                continue;
            }

            if ($child->localName === 'softHyphen') {
                continue;
            }

            if ($child->localName === 'ptab') {
                $plainParts[] = "\u{2003}";
                $htmlParts[] = "\u{2003}";

                continue;
            }

            if ($child->localName === 'sym') {
                $symbol = OoxmlXml::symChar(OoxmlXml::attr($child, 'char'), OoxmlXml::attr($child, 'font'));
                if ($symbol !== '') {
                    $plainParts[] = $symbol;
                    $htmlParts[] = htmlspecialchars($symbol, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }

                continue;
            }

            if (in_array($child->localName, [OoxmlTags::FOOTNOTE_REFERENCE, OoxmlTags::ENDNOTE_REFERENCE], true)) {
                $id = OoxmlXml::attr($child, 'id') ?? '';
                $kind = $child->localName === OoxmlTags::FOOTNOTE_REFERENCE ? 'footnote' : 'endnote';
                $htmlParts[] = '<sup class="doc-note-ref" data-doc-'.$kind.'-id="'.e($id).'">['.e($id).']</sup>';

                continue;
            }

            if ($context !== null) {
                $context->warn(
                    'unhandled_run_child',
                    sprintf('Unhandled OOXML run child <%s>', $child->localName),
                );
            }
        }

        return [
            'html' => implode('', $htmlParts),
            'plain' => implode('', $plainParts),
            'inline' => $inline,
            'run' => $run,
        ];
    }

    private function shouldSkipContainerChild(string $localName): bool
    {
        return in_array($localName, [
            'pPr',
            'sectPr',
            OoxmlTags::BOOKMARK_START,
            OoxmlTags::BOOKMARK_END,
        ], true);
    }
}

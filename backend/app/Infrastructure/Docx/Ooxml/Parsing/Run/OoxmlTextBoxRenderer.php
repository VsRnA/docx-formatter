<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing\Run;

use App\Domain\Docx\ValueObject\ParseContext;
use App\Infrastructure\Docx\Ooxml\OoxmlPackage;
use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlAnchorLayoutParser;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlCss;
use DOMElement;

final class OoxmlTextBoxRenderer
{
    public function __construct(
        private readonly OoxmlAnchorLayoutParser $anchors,
    ) {}

    /**
     * @param  callable(DOMElement): array{html: string, plain: string}  $parseParagraph
     * @param  list<array{marker: string, relationship_id: string, local_path: ?string, unsupported?: bool, attributes: array<string, mixed>}>|null  $pendingImages
     */
    public function renderFromScope(
        DOMElement $scope,
        callable $parseParagraph,
        ?string $paragraphStyleId,
        ?OoxmlPackage $package,
        ?array &$pendingImages,
        ?ParseContext $context = null,
        bool $flowLayout = false,
    ): string {
        $xpath = OoxmlXml::xpath($scope->ownerDocument);
        $nodes = $xpath->query('.//*[local-name()="txbxContent"]', $scope);
        if (! $nodes) {
            return '';
        }

        $parts = [];
        foreach ($nodes as $textBox) {
            if (! $textBox instanceof DOMElement) {
                continue;
            }

            if (OoxmlXml::isInsideMarkupCompatibilityFallback($textBox)) {
                continue;
            }

            [$minWidthPx, $minHeightPx] = $this->textboxSizeFromScope($textBox);
            $anchorLayout = $this->anchorLayoutFromScope($textBox);

            foreach (OoxmlXml::children($textBox, 'p') as $paragraph) {
                $parsed = $parseParagraph($paragraph);
                $plain = trim($parsed['plain']);
                $isMarker = $this->isPageMarkerText($plain, $minWidthPx, $minHeightPx);
                if (($plain === '' || $isMarker) && ! str_contains($parsed['html'], '<figure')) {
                    continue;
                }

                if ($plain === '' && str_contains($parsed['html'], 'doc-image--page-decoration')) {
                    continue;
                }

                if ($parsed['html'] === '') {
                    continue;
                }

                if (! $flowLayout && ($anchorLayout['anchored'] ?? false)) {
                    $parts[] = $this->renderAnchoredTextbox($parsed['html'], $anchorLayout, $minWidthPx, $minHeightPx);
                } else {
                    $parts[] = $this->renderFlowTextbox(
                        $parsed['html'],
                        $anchorLayout,
                        $minWidthPx,
                        $minHeightPx,
                        $flowLayout,
                    );
                }
            }
        }

        return implode('', $parts);
    }

    /**
     * @param  array{anchored?: bool, left_px?: ?int, top_px?: ?int}  $layout
     */
    private function renderFlowTextbox(
        string $html,
        array $layout,
        ?int $minWidthPx,
        ?int $minHeightPx,
        bool $flowLayout,
    ): string {
        $attrs = '';
        if ($flowLayout && ($layout['anchored'] ?? false)) {
            if (isset($layout['left_px'])) {
                $attrs .= ' data-anchor-left="'.(int) $layout['left_px'].'"';
            }
            if (isset($layout['top_px'])) {
                $attrs .= ' data-anchor-top="'.(int) $layout['top_px'].'"';
            }
        }

        return '<div class="doc-textbox"'.$attrs.OoxmlCss::styleAttribute([
            'flex: 1 1 auto',
            'min-width: 0',
            $minWidthPx ? 'min-width:'.$minWidthPx.'px' : null,
            $minHeightPx ? 'min-height:'.$minHeightPx.'px' : null,
        ]).'>'.$html.'</div>';
    }

    /**
     * @param  array{anchored?: bool, left_px?: ?int, top_px?: ?int}  $layout
     */
    private function renderAnchoredTextbox(
        string $html,
        array $layout,
        ?int $minWidthPx,
        ?int $minHeightPx,
    ): string {
        $rules = [
            'position:absolute',
            'z-index:2',
        ];

        if (isset($layout['left_px'])) {
            $rules[] = 'left:'.(int) $layout['left_px'].'px';
        }

        if (isset($layout['top_px'])) {
            $rules[] = 'top:'.(int) $layout['top_px'].'px';
        }

        if ($minWidthPx) {
            $rules[] = 'min-width:'.$minWidthPx.'px';
        }

        if ($minHeightPx) {
            $rules[] = 'min-height:'.$minHeightPx.'px';
        }

        return '<div class="doc-textbox doc-textbox--anchored"'.OoxmlCss::styleAttribute($rules).'>'.$html.'</div>';
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function textboxSizeFromScope(DOMElement $textBox): array
    {
        $node = $textBox->parentNode;

        while ($node instanceof DOMElement) {
            if ($node->localName === 'extent') {
                return [
                    $this->emuToPx(OoxmlXml::attr($node, 'cx')),
                    $this->emuToPx(OoxmlXml::attr($node, 'cy')),
                ];
            }

            $node = $node->parentNode;
        }

        return [null, null];
    }

    /**
     * @return array{
     *     anchored?: bool,
     *     left_px?: ?int,
     *     top_px?: ?int,
     *     position_h_from?: ?string,
     *     position_v_from?: ?string
     * }
     */
    private function anchorLayoutFromScope(DOMElement $textBox): array
    {
        $node = $textBox->parentNode;

        while ($node instanceof DOMElement) {
            if ($node->localName === 'anchor') {
                return $this->anchors->readAnchorLayout($node);
            }

            $node = $node->parentNode;
        }

        return [
            'anchored' => false,
            'left_px' => null,
            'top_px' => null,
        ];
    }

    private function emuToPx(?string $emu): ?int
    {
        if ($emu === null || $emu === '' || ! ctype_digit($emu)) {
            return null;
        }

        return max(1, (int) round((int) $emu / 9525));
    }

    /**
     * A bare 1-3 digit number is only treated as a page-number marker when it
     * sits in a tiny standalone textbox. Larger boxes with a number carry real
     * content (callout numbers, quantities) and must be kept.
     */
    private function isPageMarkerText(string $text, ?int $widthPx, ?int $heightPx): bool
    {
        if (preg_match('/^\d{1,3}$/', trim($text)) !== 1) {
            return false;
        }

        $narrow = $widthPx !== null && $widthPx <= 60;
        $short = $heightPx !== null && $heightPx <= 40;

        return $narrow && $short;
    }
}

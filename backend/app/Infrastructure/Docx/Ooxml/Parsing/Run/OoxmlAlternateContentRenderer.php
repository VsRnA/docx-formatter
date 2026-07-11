<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing\Run;

use App\Domain\Docx\ValueObject\ParseContext;
use App\Infrastructure\Docx\Ooxml\OoxmlPackage;
use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use App\Infrastructure\Docx\Ooxml\Parsing\Layout\SymbolRowLayout;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlCss;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlImageBlockFactory;
use DOMElement;

final class OoxmlAlternateContentRenderer
{
    public function __construct(
        private readonly OoxmlImageBlockFactory $images,
        private readonly OoxmlTextBoxRenderer $textBoxes,
        private readonly OoxmlAnchorShapeRenderer $shapes,
        private readonly SymbolRowLayout $symbolRows,
    ) {}

    /**
     * @param  callable(DOMElement): array{html: string, plain: string}  $parseParagraph
     * @param  list<array{marker: string, relationship_id: string, local_path: ?string, unsupported?: bool, attributes: array<string, mixed>}>  $pendingImages
     */
    public function renderAlternateContent(
        DOMElement $alternateContent,
        callable $parseParagraph,
        ?string $paragraphStyleId,
        OoxmlPackage $package,
        array &$pendingImages,
        ?ParseContext $context = null,
    ): string {
        $choice = OoxmlXml::child($alternateContent, 'Choice');
        $fallback = OoxmlXml::child($alternateContent, 'Fallback');

        $textHtml = '';
        $pageHtml = '';
        $flowHtml = '';

        if ($choice instanceof DOMElement) {
            foreach ($choice->childNodes as $branchChild) {
                if (! $branchChild instanceof DOMElement || $branchChild->localName !== 'drawing') {
                    continue;
                }

                $textHtml .= $this->textBoxes->renderFromScope(
                    $branchChild,
                    $parseParagraph,
                    $paragraphStyleId,
                    $package,
                    $pendingImages,
                    $context,
                    flowLayout: true,
                );
                $figures = $this->images->figuresFromScope($package, $branchChild, $pendingImages, $context);
                $pageHtml .= $figures['page'];
                $flowHtml .= $figures['flow'];
                $pageHtml .= $this->shapes->renderFromScope($branchChild);
            }
        }

        if ($fallback instanceof DOMElement) {
            foreach ($fallback->childNodes as $branchChild) {
                if (! $branchChild instanceof DOMElement) {
                    continue;
                }

                if (! in_array($branchChild->localName, ['pict', 'drawing'], true)) {
                    continue;
                }

                $figures = $this->images->figuresFromScope($package, $branchChild, $pendingImages, $context);
                if ($pageHtml !== '' && $figures['flow'] !== '') {
                    $pageHtml .= $this->promoteMarginTabFigures($figures['flow']);
                } else {
                    $pageHtml .= $figures['page'];
                    $flowHtml .= $figures['flow'];
                }
            }
        }

        if ($textHtml === '' && $flowHtml === '' && $pageHtml === '') {
            return '';
        }

        if ($textHtml !== '') {
            return $pageHtml.OoxmlCss::symbolRowOpen().$this->symbolRows->wrapSymbolIcons($flowHtml).$textHtml.'</div>';
        }

        return $pageHtml.$flowHtml;
    }

    /**
     * @param  callable(DOMElement): array{html: string, plain: string}  $parseParagraph
     * @param  list<array{marker: string, relationship_id: string, local_path: ?string, unsupported?: bool, attributes: array<string, mixed>}>  $pendingImages
     */
    public function renderGraphicScope(
        DOMElement $scope,
        callable $parseParagraph,
        ?string $paragraphStyleId,
        OoxmlPackage $package,
        array &$pendingImages,
        ?ParseContext $context = null,
        bool $includeTextboxes = true,
    ): string {
        $figures = $this->images->figuresFromScope($package, $scope, $pendingImages, $context);
        $shapeHtml = $this->shapes->renderFromScope($scope);
        $textHtml = $includeTextboxes
            ? $this->textBoxes->renderFromScope(
                $scope,
                $parseParagraph,
                $paragraphStyleId,
                $package,
                $pendingImages,
                $context,
                flowLayout: $figures['flow'] !== '',
            )
            : '';

        if ($figures['page'] === '' && $figures['flow'] === '' && $textHtml === '' && $shapeHtml === '') {
            return '';
        }

        if ($textHtml !== '') {
            return $figures['page'].$shapeHtml.OoxmlCss::symbolRowOpen().$this->symbolRows->wrapSymbolIcons($figures['flow']).$textHtml.'</div>';
        }

        return $figures['page'].$figures['flow'].$shapeHtml;
    }

    private function promoteMarginTabFigures(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $promoted = preg_replace(
            '/class="doc-image doc-image--inline"/',
            'class="doc-image doc-image--page-decoration doc-image--page-decoration-left"',
            $html,
            1,
        );

        return is_string($promoted) ? $promoted : $html;
    }
}

<?php

namespace App\Infrastructure\Docx\Ooxml\Styles;

use App\Infrastructure\Docx\Ooxml\OoxmlNamespaces;
use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use DOMDocument;
use DOMElement;

/**
 * Resolves paragraph/run properties from word/styles.xml including basedOn chain.
 */
final class OoxmlStyleResolver
{
    /** @var array<string, array{basedOn: ?string, name: ?string, pPr: ?DOMElement, rPr: ?DOMElement}> */
    private array $styles = [];

    private ?DOMElement $docDefaultPPr = null;

    private ?DOMElement $docDefaultRPr = null;

    public function load(?DOMDocument $stylesDocument): void
    {
        $this->styles = [];
        $this->docDefaultPPr = null;
        $this->docDefaultRPr = null;

        if ($stylesDocument === null) {
            return;
        }

        $docDefaults = $stylesDocument->getElementsByTagNameNS(OoxmlNamespaces::W, 'docDefaults')->item(0);
        if ($docDefaults instanceof DOMElement) {
            $rPrDefault = OoxmlXml::child($docDefaults, 'rPrDefault');
            if ($rPrDefault instanceof DOMElement) {
                $this->docDefaultRPr = OoxmlXml::child($rPrDefault, 'rPr');
            }

            $pPrDefault = OoxmlXml::child($docDefaults, 'pPrDefault');
            if ($pPrDefault instanceof DOMElement) {
                $this->docDefaultPPr = OoxmlXml::child($pPrDefault, 'pPr');
            }
        }

        foreach ($stylesDocument->getElementsByTagNameNS(OoxmlNamespaces::W, 'style') as $style) {
            if (! $style instanceof DOMElement) {
                continue;
            }
            $styleId = OoxmlXml::attr($style, 'styleId');
            if (! $styleId) {
                continue;
            }

            $basedOn = OoxmlXml::child($style, 'basedOn');
            $nameNode = OoxmlXml::child($style, 'name');

            $this->styles[$styleId] = [
                'basedOn' => $basedOn ? OoxmlXml::attr($basedOn, 'val') : null,
                'name' => $nameNode ? OoxmlXml::attr($nameNode, 'val') : null,
                'pPr' => OoxmlXml::child($style, 'pPr'),
                'rPr' => OoxmlXml::child($style, 'rPr'),
            ];
        }
    }

    public function headingLevel(?string $styleId): ?int
    {
        if ($styleId === null) {
            return null;
        }

        if (preg_match('/^heading(\d)$/i', $styleId, $matches)) {
            return min(6, max(1, (int) $matches[1]));
        }
        if (strcasecmp($styleId, 'title') === 0) {
            return 1;
        }

        $name = $this->resolveStyleName($styleId);
        if ($name && preg_match('/heading\s*(\d)/i', $name, $matches)) {
            return min(6, max(1, (int) $matches[1]));
        }
        if ($name && stripos($name, 'title') !== false) {
            return 1;
        }

        return null;
    }

    public function resolveStyleName(?string $styleId): ?string
    {
        if ($styleId === null) {
            return null;
        }

        $chain = $this->styleChain($styleId);
        foreach (array_reverse($chain) as $id) {
            $name = $this->styles[$id]['name'] ?? null;
            if ($name) {
                return $name;
            }
        }

        return $styleId;
    }

    /**
     * @return list<string> CSS declarations
     */
    public function paragraphCss(?DOMElement $directPPr, ?string $styleId): array
    {
        $rules = [];

        foreach ($this->collectParagraphPropertyNodes($directPPr, $styleId) as $pPr) {
            $rules = array_merge($rules, $this->cssFromParagraphProperties($pPr));
        }

        return array_values(array_unique($rules));
    }

    /**
     * Document-wide defaults from w:docDefaults for page shell / typography.
     *
     * @return array{font?: string, size_pt?: float, line_height?: float, color?: string}
     */
    public function documentDefaults(): array
    {
        $run = $this->docDefaultRPr
            ? $this->runProperties($this->docDefaultRPr)
            : [
                'font' => null,
                'sizePt' => null,
                'color' => null,
            ];

        $lineHeight = null;
        if ($this->docDefaultPPr) {
            $spacing = OoxmlXml::child($this->docDefaultPPr, 'spacing');
            if ($spacing) {
                $line = OoxmlXml::attr($spacing, 'line');
                $lineRule = OoxmlXml::attr($spacing, 'lineRule') ?? 'auto';
                if (is_numeric($line) && $lineRule === 'auto') {
                    $lineHeight = round(((int) $line) / 240, 2);
                }
            }
        }

        return array_filter([
            'font' => $run['font'],
            'size_pt' => $run['sizePt'],
            'line_height' => $lineHeight,
            'color' => $run['color'],
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * Run defaults from the paragraph style merged with an explicit character
     * style (w:rStyle). The character style overlays the paragraph style.
     *
     * @return array{bold: bool, italic: bool, underline: bool, strike: bool, superscript: bool, subscript: bool, color: ?string, sizePt: ?float, font: ?string}
     */
    public function combinedRunDefaults(?string $paragraphStyleId, ?string $characterStyleId): array
    {
        $defaults = $this->runDefaults($paragraphStyleId);

        if ($characterStyleId !== null) {
            $defaults = $this->mergeRunDefaults($defaults, $this->runDefaults($characterStyleId));
        }

        return $defaults;
    }

    /**
     * @return array{bold: bool, italic: bool, underline: bool, strike: bool, superscript: bool, subscript: bool, color: ?string, sizePt: ?float, font: ?string}
     */
    public function runDefaults(?string $styleId): array
    {
        $defaults = [
            'bold' => false,
            'italic' => false,
            'underline' => false,
            'strike' => false,
            'superscript' => false,
            'subscript' => false,
            'color' => null,
            'sizePt' => null,
            'font' => null,
        ];

        if ($this->docDefaultRPr) {
            $defaults = $this->mergeRunDefaults($defaults, $this->runProperties($this->docDefaultRPr));
        }

        foreach ($this->styleChain($styleId) as $id) {
            $rPr = $this->styles[$id]['rPr'] ?? null;
            if ($rPr) {
                $defaults = $this->mergeRunDefaults($defaults, $this->runProperties($rPr));
            }
        }

        return $defaults;
    }

    /**
     * @return list<DOMElement>
     */
    private function collectParagraphPropertyNodes(?DOMElement $directPPr, ?string $styleId): array
    {
        $nodes = [];
        if ($this->docDefaultPPr) {
            $nodes[] = $this->docDefaultPPr;
        }

        foreach (array_reverse($this->styleChain($styleId)) as $id) {
            $pPr = $this->styles[$id]['pPr'] ?? null;
            if ($pPr) {
                $nodes[] = $pPr;
            }
        }
        if ($directPPr) {
            $nodes[] = $directPPr;
        }

        return $nodes;
    }

    /**
     * @return list<string>
     */
    private function styleChain(?string $styleId): array
    {
        if ($styleId === null) {
            return [];
        }

        $chain = [];
        $current = $styleId;
        $guard = 0;
        while ($current && isset($this->styles[$current]) && $guard < 20) {
            $chain[] = $current;
            $current = $this->styles[$current]['basedOn'] ?? null;
            $guard++;
        }

        return array_reverse($chain);
    }

    /**
     * @return list<string>
     */
    private function cssFromParagraphProperties(DOMElement $pPr): array
    {
        $rules = [];

        $jc = OoxmlXml::child($pPr, 'jc');
        if ($jc) {
            $align = match (OoxmlXml::attr($jc, 'val')) {
                'center' => 'text-align: center',
                'right' => 'text-align: right',
                'both', 'justify' => 'text-align: justify',
                'left' => 'text-align: left',
                default => null,
            };
            if ($align) {
                $rules[] = $align;
            }
        }

        $ind = OoxmlXml::child($pPr, 'ind');
        if ($ind) {
            $left = OoxmlXml::twipsToPt(OoxmlXml::attr($ind, 'left'));
            if ($left) {
                $rules[] = 'margin-left: '.$left.'pt';
            }
            $right = OoxmlXml::twipsToPt(OoxmlXml::attr($ind, 'right'));
            if ($right) {
                $rules[] = 'margin-right: '.$right.'pt';
            }
            $first = OoxmlXml::twipsToPt(OoxmlXml::attr($ind, 'firstLine'));
            if ($first) {
                $rules[] = 'text-indent: '.$first.'pt';
            }
            $hanging = OoxmlXml::twipsToPt(OoxmlXml::attr($ind, 'hanging'));
            if ($hanging) {
                $rules[] = 'padding-left: '.$hanging.'pt';
                $rules[] = 'text-indent: -'.$hanging.'pt';
            }
        }

        $spacing = OoxmlXml::child($pPr, 'spacing');
        if ($spacing) {
            $before = OoxmlXml::twipsToPt(OoxmlXml::attr($spacing, 'before'));
            if ($before) {
                $rules[] = 'margin-top: '.$before.'pt';
            }
            $after = OoxmlXml::twipsToPt(OoxmlXml::attr($spacing, 'after'));
            if ($after) {
                $rules[] = 'margin-bottom: '.$after.'pt';
            }
            $line = OoxmlXml::attr($spacing, 'line');
            $lineRule = OoxmlXml::attr($spacing, 'lineRule') ?? 'auto';
            if (is_numeric($line) && $lineRule === 'auto') {
                $rules[] = 'line-height: '.round(((int) $line) / 240, 2);
            }
        }

        return $rules;
    }

    /**
     * @param  array{bold: bool, italic: bool, underline: bool, strike: bool, superscript: bool, subscript: bool, color: ?string, sizePt: ?float, font: ?string}  $base
     * @param  array{bold: bool, italic: bool, underline: bool, strike: bool, superscript: bool, subscript: bool, color: ?string, sizePt: ?float, font: ?string}  $overlay
     * @return array{bold: bool, italic: bool, underline: bool, strike: bool, superscript: bool, subscript: bool, color: ?string, sizePt: ?float, font: ?string}
     */
    private function mergeRunDefaults(array $base, array $overlay): array
    {
        foreach ($overlay as $key => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * @return array{bold: bool, italic: bool, underline: bool, strike: bool, superscript: bool, subscript: bool, color: ?string, sizePt: ?float, font: ?string}
     */
    private function runProperties(DOMElement $rPr): array
    {
        $fonts = OoxmlXml::child($rPr, 'rFonts');
        $color = OoxmlXml::child($rPr, 'color');
        $vertAlign = OoxmlXml::child($rPr, 'vertAlign');
        $sizeVal = OoxmlXml::sizeHalfPointsFromRPr($rPr);

        return [
            'bold' => OoxmlXml::onOff($rPr, 'b'),
            'italic' => OoxmlXml::onOff($rPr, 'i'),
            'underline' => OoxmlXml::onOff($rPr, 'u'),
            'strike' => OoxmlXml::onOff($rPr, 'strike'),
            'superscript' => $vertAlign && OoxmlXml::attr($vertAlign, 'val') === 'superscript',
            'subscript' => $vertAlign && OoxmlXml::attr($vertAlign, 'val') === 'subscript',
            'color' => $color ? OoxmlXml::attr($color, 'val') : null,
            'sizePt' => is_numeric($sizeVal) ? round(((float) $sizeVal) / 2, 1) : null,
            'font' => OoxmlXml::fontFamilyFromRFonts($fonts),
        ];
    }
}

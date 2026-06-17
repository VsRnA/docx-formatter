<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing\Run;

use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlCss;
use App\Infrastructure\Docx\Ooxml\Styles\OoxmlStyleResolver;
use DOMElement;

final class OoxmlRunTextFormatter
{
    public function __construct(
        private readonly OoxmlStyleResolver $styles,
    ) {}

    /**
     * @param  array<string, mixed>  $inline
     */
    public function formatPlainWithRun(
        string $plain,
        DOMElement $run,
        ?string $paragraphStyleId,
        array &$inline,
    ): string {
        if ($run->localName === 'hyperlink') {
            $href = OoxmlXml::attr($run, 'anchor') ?? OoxmlXml::attr($run, 'id') ?? '#';

            return '<a href="'.e($href).'">'.$this->formatText($plain, OoxmlXml::child($run, 'rPr'), $paragraphStyleId, $inline).'</a>';
        }

        return $this->formatText($plain, OoxmlXml::child($run, 'rPr'), $paragraphStyleId, $inline);
    }

    /**
     * @param  array<string, mixed>  $inline
     */
    public function formatText(string $text, ?DOMElement $rPr, ?string $paragraphStyleId, array &$inline): string
    {
        $characterStyleId = null;
        if ($rPr) {
            $rStyle = OoxmlXml::child($rPr, 'rStyle');
            $characterStyleId = $rStyle ? OoxmlXml::attr($rStyle, 'val') : null;
        }

        $props = $this->styles->combinedRunDefaults($paragraphStyleId, $characterStyleId);
        if ($rPr) {
            $props = $this->overlayRunProperties($props, $rPr);
        }

        $content = e($text);
        if ($props['bold']) {
            $content = '<strong>'.$content.'</strong>';
            $inline['has_bold'] = true;
        }
        if ($props['italic']) {
            $content = '<em>'.$content.'</em>';
            $inline['has_italic'] = true;
        }
        if ($props['underline']) {
            $content = '<u>'.$content.'</u>';
        }
        if ($props['strike']) {
            $content = '<s>'.$content.'</s>';
        }
        if ($props['superscript']) {
            $content = '<sup>'.$content.'</sup>';
        }
        if ($props['subscript']) {
            $content = '<sub>'.$content.'</sub>';
        }

        $css = [];
        if ($props['color'] && ! in_array(strtolower($props['color']), ['auto', '000000'], true)) {
            $css[] = 'color:#'.ltrim($props['color'], '#');
        }
        if ($props['sizePt']) {
            $css[] = 'font-size:'.$props['sizePt'].'pt';
        }
        if ($props['font']) {
            $css[] = OoxmlCss::fontFamily($props['font']);
        }

        if ($css !== []) {
            $content = '<span'.OoxmlCss::styleAttribute($css).'>'.$content.'</span>';
        }

        return $content;
    }

    /**
     * @param  array{bold: bool, italic: bool, underline: bool, strike: bool, superscript: bool, subscript: bool, color: ?string, sizePt: ?float, font: ?string}  $base
     * @return array{bold: bool, italic: bool, underline: bool, strike: bool, superscript: bool, subscript: bool, color: ?string, sizePt: ?float, font: ?string}
     */
    private function overlayRunProperties(array $base, DOMElement $rPr): array
    {
        $fonts = OoxmlXml::child($rPr, 'rFonts');
        $color = OoxmlXml::child($rPr, 'color');
        $vertAlign = OoxmlXml::child($rPr, 'vertAlign');
        $sizeVal = OoxmlXml::sizeHalfPointsFromRPr($rPr);

        if (OoxmlXml::child($rPr, 'b')) {
            $base['bold'] = OoxmlXml::onOff($rPr, 'b');
        }
        if (OoxmlXml::child($rPr, 'i')) {
            $base['italic'] = OoxmlXml::onOff($rPr, 'i');
        }
        if (OoxmlXml::child($rPr, 'u')) {
            $base['underline'] = OoxmlXml::onOff($rPr, 'u');
        }
        if (OoxmlXml::child($rPr, 'strike')) {
            $base['strike'] = OoxmlXml::onOff($rPr, 'strike');
        }
        if ($vertAlign) {
            $base['superscript'] = OoxmlXml::attr($vertAlign, 'val') === 'superscript';
            $base['subscript'] = OoxmlXml::attr($vertAlign, 'val') === 'subscript';
        }
        if ($color) {
            $base['color'] = OoxmlXml::attr($color, 'val');
        }
        if (is_numeric($sizeVal)) {
            $base['sizePt'] = round(((float) $sizeVal) / 2, 1);
        }
        if ($fonts) {
            $base['font'] = OoxmlXml::fontFamilyFromRFonts($fonts);
        }

        return $base;
    }
}

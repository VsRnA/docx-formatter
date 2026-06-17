<?php

namespace App\Infrastructure\Docx\Ooxml\Styles;

use App\Infrastructure\Docx\Ooxml\OoxmlNamespaces;
use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use DOMDocument;
use DOMElement;

/**
 * Resolves w:numPr → list marker style from word/numbering.xml.
 */
final class OoxmlNumberingResolver
{
    /** @var array<string, string> numId → abstractNumId */
    private array $numMap = [];

    /** @var array<string, array<string, array{fmt: string, text: string, font: ?string, pPr: ?DOMElement}>> */
    private array $abstractLevels = [];

    public function load(?DOMDocument $numbering): void
    {
        $this->numMap = [];
        $this->abstractLevels = [];

        if ($numbering === null) {
            return;
        }

        foreach ($numbering->getElementsByTagNameNS(OoxmlNamespaces::W, 'num') as $num) {
            if (! $num instanceof DOMElement) {
                continue;
            }
            $numId = OoxmlXml::attr($num, 'numId');
            $abstract = OoxmlXml::child($num, 'abstractNumId');
            $abstractId = $abstract ? OoxmlXml::attr($abstract, 'val') : null;
            if ($numId !== null && $abstractId !== null) {
                $this->numMap[$numId] = $abstractId;
            }
        }

        foreach ($numbering->getElementsByTagNameNS(OoxmlNamespaces::W, 'abstractNum') as $abstract) {
            if (! $abstract instanceof DOMElement) {
                continue;
            }
            $abstractId = OoxmlXml::attr($abstract, 'abstractNumId');
            if ($abstractId === null) {
                continue;
            }

            foreach ($abstract->getElementsByTagNameNS(OoxmlNamespaces::W, 'lvl') as $lvl) {
                if (! $lvl instanceof DOMElement) {
                    continue;
                }
                $ilvl = OoxmlXml::attr($lvl, 'ilvl') ?? '0';
                $fmtNode = OoxmlXml::child($lvl, 'numFmt');
                $textNode = OoxmlXml::child($lvl, 'lvlText');
                $fonts = OoxmlXml::child($lvl, 'rPr');
                $fontNode = $fonts ? OoxmlXml::child($fonts, 'rFonts') : null;

                $this->abstractLevels[$abstractId][$ilvl] = [
                    'fmt' => $fmtNode ? (OoxmlXml::attr($fmtNode, 'val') ?? 'bullet') : 'bullet',
                    'text' => $textNode ? (OoxmlXml::attr($textNode, 'val') ?? '') : '',
                    'font' => OoxmlXml::fontFamilyFromRFonts($fontNode),
                    'pPr' => OoxmlXml::child($lvl, 'pPr'),
                ];
            }
        }
    }

    public function levelParagraphProperties(?string $numId, ?string $ilvl = null): ?DOMElement
    {
        if ($numId === null || $numId === '0') {
            return null;
        }

        $abstractId = $this->numMap[$numId] ?? null;
        if ($abstractId === null) {
            return null;
        }

        $level = $this->abstractLevels[$abstractId][$ilvl ?? '0']
            ?? $this->abstractLevels[$abstractId]['0']
            ?? null;

        return $level['pPr'] ?? null;
    }

    public function resolveMarker(?string $numId, ?string $ilvl = null): string
    {
        if ($numId === null || $numId === '0') {
            return 'disc';
        }

        $abstractId = $this->numMap[$numId] ?? null;
        if ($abstractId === null) {
            return 'disc';
        }

        $level = $this->abstractLevels[$abstractId][$ilvl ?? '0']
            ?? $this->abstractLevels[$abstractId]['0']
            ?? null;

        if ($level === null) {
            return 'disc';
        }

        return $this->classifyMarker($level['fmt'], $level['text'], $level['font']);
    }

    private function classifyMarker(string $numFmt, string $lvlText, ?string $font): string
    {
        if (in_array($numFmt, ['decimal', 'lowerLetter', 'upperLetter', 'lowerRoman', 'upperRoman'], true)) {
            return 'decimal';
        }

        if ($lvlText === '-' || $lvlText === '–' || $lvlText === '—') {
            return 'dash';
        }

        $code = $lvlText !== '' ? mb_ord(mb_substr($lvlText, 0, 1)) : null;
        if (in_array($code, [0xF02D, 0x2013, 0x2014, 0x2D], true)) {
            return 'dash';
        }

        if (in_array($code, [0xF0B7, 0x2022, 0xB7], true)) {
            return 'disc';
        }

        return ($font === 'Symbol' && $code === 0xF02D) ? 'dash' : 'disc';
    }
}

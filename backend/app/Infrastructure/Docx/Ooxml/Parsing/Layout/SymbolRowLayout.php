<?php

namespace App\Infrastructure\Docx\Ooxml\Parsing\Layout;

use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlCss;

final class SymbolRowLayout
{
    /** @param  list<string>  $parts
     * @return list<string>
     */
    public function consolidate(array $parts): array
    {
        $parts = $this->distributeFiguresToSymbolRows($parts);
        $parts = $this->wrapFigureTextRows($parts);

        return $this->mergeTrailingTextIntoSymbolRows($parts);
    }

    public function wrapSymbolIcons(string $figuresHtml): string
    {
        if ($figuresHtml === '' || ! str_contains($figuresHtml, '<figure')) {
            return $figuresHtml;
        }

        if (str_contains($figuresHtml, 'doc-symbol-icons')) {
            return $figuresHtml;
        }

        $horizontal = substr_count($figuresHtml, '<figure') > 1;

        return OoxmlCss::symbolIconsOpen($horizontal).$figuresHtml.'</div>';
    }

    /** @param  list<string>  $parts
     * @return list<string>
     */
    private function distributeFiguresToSymbolRows(array $parts): array
    {
        $rows = [];
        $figures = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if ($this->isStandaloneFigurePart($part)) {
                $figures[] = $part;

                continue;
            }

            $rows[] = $part;
        }

        if ($figures === []) {
            return $rows;
        }

        $targets = [];
        foreach ($rows as $index => $row) {
            if (! $this->isSymbolRowWithoutFigure($row)) {
                continue;
            }

            $targets[] = [
                'index' => $index,
                'anchor' => $this->anchorLeftFromRow($row),
            ];
        }

        usort($targets, static function (array $left, array $right): int {
            $leftKey = $left['anchor'] ?? PHP_INT_MAX;
            $rightKey = $right['anchor'] ?? PHP_INT_MAX;

            return $leftKey <=> $rightKey;
        });

        $figureIndex = 0;
        foreach ($targets as $target) {
            if (! isset($figures[$figureIndex])) {
                break;
            }

            $rows[$target['index']] = $this->insertFigureIntoSymbolRow(
                $rows[$target['index']],
                $figures[$figureIndex],
            );
            $figureIndex++;
        }

        while ($figureIndex < count($figures)) {
            $rows[] = $figures[$figureIndex];
            $figureIndex++;
        }

        return $rows;
    }

    private function anchorLeftFromRow(string $row): ?int
    {
        if (preg_match('/\bdata-anchor-left="(\d+)"/', $row, $match)) {
            return (int) $match[1];
        }

        return null;
    }

    /** @param  list<string>  $parts
     * @return list<string>
     */
    private function wrapFigureTextRows(array $parts): array
    {
        $wrapped = [];
        $pendingFigure = '';

        foreach ($parts as $part) {
            if ($this->isStandaloneFigurePart($part)) {
                $pendingFigure .= $part;

                continue;
            }

            if ($pendingFigure !== '' && trim(strip_tags($part)) !== '') {
                $wrapped[] = OoxmlCss::symbolRowOpen().$this->wrapSymbolIcons($pendingFigure).$part.'</div>';
                $pendingFigure = '';

                continue;
            }

            if ($pendingFigure !== '') {
                $wrapped[] = $this->wrapSymbolIcons($pendingFigure);
                $pendingFigure = '';
            }

            $wrapped[] = $part;
        }

        if ($pendingFigure !== '') {
            $wrapped[] = $pendingFigure;
        }

        return $wrapped;
    }

    private function isStandaloneFigurePart(string $html): bool
    {
        if (! str_contains($html, '<figure class="doc-image')) {
            return false;
        }

        return ! str_contains($html, 'doc-symbol-row') && trim(strip_tags($html)) === '';
    }

    private function insertFigureIntoSymbolRow(string $symbolRow, string $figure): string
    {
        if (str_contains($symbolRow, 'doc-symbol-icons')) {
            $replaced = preg_replace('/(<div class="doc-symbol-icons"[^>]*>)/', '$1'.$figure, $symbolRow, 1);

            return is_string($replaced) ? $replaced : $symbolRow;
        }

        $replaced = preg_replace(
            '/(<div class="doc-symbol-row"[^>]*>)/',
            '$1'.$this->wrapSymbolIcons($figure),
            $symbolRow,
            1,
        );

        return is_string($replaced) ? $replaced : $symbolRow;
    }

    private function isSymbolRowWithoutFigure(string $html): bool
    {
        return str_contains($html, 'doc-symbol-row') && ! str_contains($html, '<figure');
    }

    /** @param  list<string>  $parts
     * @return list<string>
     */
    private function mergeTrailingTextIntoSymbolRows(array $parts): array
    {
        $merged = [];
        $index = 0;

        while ($index < count($parts)) {
            $part = $parts[$index];
            $next = $parts[$index + 1] ?? '';

            if (
                str_contains($part, 'doc-symbol-row')
                && $next !== ''
                && trim(strip_tags($next)) !== ''
                && ! str_contains($next, '<figure')
                && ! str_contains($next, 'doc-symbol-row')
            ) {
                $partPlain = trim(preg_replace('/\s+/u', ' ', strip_tags($part)) ?? '');
                $nextPlain = trim(preg_replace('/\s+/u', ' ', strip_tags($next)) ?? '');
                if ($nextPlain !== '' && str_contains($partPlain, $nextPlain)) {
                    $merged[] = $part;
                    $index += 2;

                    continue;
                }

                $merged[] = $this->insertTextIntoSymbolRow($part, $next);
                $index += 2;

                continue;
            }

            $merged[] = $part;
            $index++;
        }

        return $merged;
    }

    private function insertTextIntoSymbolRow(string $symbolRow, string $textHtml): string
    {
        $incomingPlain = trim(preg_replace('/\s+/u', ' ', strip_tags($textHtml)) ?? '');
        if ($incomingPlain === '') {
            return $symbolRow;
        }

        if (str_contains($symbolRow, 'doc-textbox')) {
            $replaced = preg_replace_callback(
                '/(<div class="doc-textbox"[^>]*>)(.*?)(<\/div>\s*<\/div>\s*)$/s',
                function (array $matches) use ($textHtml, $incomingPlain): string {
                    $innerPlain = trim(preg_replace('/\s+/u', ' ', strip_tags($matches[2])) ?? '');
                    if ($innerPlain !== '' && str_contains($innerPlain, $incomingPlain)) {
                        return $matches[0];
                    }

                    if ($innerPlain === '') {
                        return $matches[1].$textHtml.$matches[3];
                    }

                    return $matches[1].$matches[2].$textHtml.$matches[3];
                },
                $symbolRow,
                1,
            );

            return is_string($replaced) ? $replaced : $symbolRow;
        }

        $replaced = preg_replace(
            '/(<div class="doc-symbol-row"[^>]*>.*?<\/div>\s*)(<\/div>\s*)$/s',
            '$1'.OoxmlCss::textboxOpen().$textHtml.'</div>$2',
            $symbolRow,
            1,
        );

        return is_string($replaced) ? $replaced : $symbolRow.$textHtml;
    }
}

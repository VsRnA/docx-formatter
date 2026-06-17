<?php

namespace App\Console\Commands;

use App\Domain\Docx\Service\CoveragePolicy;
use App\Infrastructure\Document\CoverageSourceTextExtractor;
use App\Infrastructure\Docx\Ooxml\OoxmlNativeDocxParser;
use App\Infrastructure\Docx\Ooxml\OoxmlXml;
use Illuminate\Console\Command;

class InspectDocxCommand extends Command
{
    protected $signature = 'docx:inspect {path : Path to .docx file}';

    protected $description = 'Parse DOCX, print blocks, coverage and missing text fragments';

    public function handle(
        OoxmlNativeDocxParser $parser,
        CoverageSourceTextExtractor $sourceText,
        CoveragePolicy $coverage,
    ): int {
        $path = $this->argument('path');
        if (! is_file($path)) {
            $this->error('File not found: '.$path);

            return self::FAILURE;
        }

        $parsed = $parser->parse($path);
        $sourceFragments = $sourceText->extractFragments($path);
        $sourcePlain = OoxmlXml::normalizePlainText(implode(' ', $sourceFragments));

        $blockParts = [];
        foreach ($parsed->blocks as $block) {
            if ($block->textOriginal) {
                $blockParts[] = $block->textOriginal;
            }
        }
        $blocksPlain = OoxmlXml::normalizePlainText(implode(' ', $blockParts));
        $missing = $sourceText->findMissingFragments($sourceFragments, $blocksPlain);
        $coverageResult = $coverage->evaluate(
            $sourcePlain,
            $blocksPlain,
            PARSE_COVERAGE_THRESHOLD,
            $missing,
        );

        $this->info('Title: '.$parsed->title);
        $this->info('Blocks: '.count($parsed->blocks));
        $this->info(sprintf(
            'Coverage: %.2f%% (%d / %d chars, threshold %.0f%%)',
            $coverageResult->coverageRatio * 100,
            $coverageResult->blocksCharCount,
            $coverageResult->sourceCharCount,
            PARSE_COVERAGE_THRESHOLD * 100,
        ));

        foreach ($parsed->blocks as $block) {
            $region = $block->meta['region'] ?? 'body';
            $this->line(sprintf(
                '  [%d] %s (%s) | %s',
                $block->sort,
                $block->type->value,
                $region,
                mb_substr(strip_tags($block->textOriginal ?? $block->html ?? ''), 0, 80),
            ));
        }

        if ($missing !== []) {
            $this->warn('Missing fragments ('.count($missing).'):');
            foreach (array_slice($missing, 0, 20) as $fragment) {
                $this->line('  - '.mb_substr($fragment, 0, 120));
            }
            if (count($missing) > 20) {
                $this->line('  ... and '.(count($missing) - 20).' more');
            }
        }

        if ($parsed->meta['warnings'] ?? []) {
            $this->warn('Parse warnings:');
            foreach ($parsed->meta['warnings'] as $warning) {
                $this->line('  - '.($warning['type'] ?? '').': '.($warning['message'] ?? ''));
            }
        }

        return $coverageResult->passesThreshold ? self::SUCCESS : self::FAILURE;
    }
}

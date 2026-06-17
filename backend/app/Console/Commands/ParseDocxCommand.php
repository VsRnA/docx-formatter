<?php

namespace App\Console\Commands;

use App\Infrastructure\Docx\Ooxml\OoxmlNativeDocxParser;
use Illuminate\Console\Command;

class ParseDocxCommand extends Command
{
    protected $signature = 'docx:parse {path : Path to .docx inside container}';

    protected $description = 'Parse a DOCX file and print block structure (no Yandex, no DB)';

    public function handle(OoxmlNativeDocxParser $parser): int
    {
        $path = $this->argument('path');
        if (! is_file($path)) {
            $this->error('File not found: '.$path);

            return self::FAILURE;
        }

        $parsed = $parser->parse($path);
        $this->info('Title: '.$parsed->title);
        $this->info('Blocks: '.count($parsed->blocks));

        foreach ($parsed->blocks as $block) {
            $this->line(sprintf(
                '  [%d] %s | %s',
                $block->sort,
                $block->type->value,
                mb_substr(strip_tags($block->textOriginal ?? $block->html ?? ''), 0, 80),
            ));
        }

        if ($parsed->meta['warnings'] ?? []) {
            $this->warn('Warnings:');
            foreach ($parsed->meta['warnings'] as $w) {
                $this->line('  - '.($w['type'] ?? '').': '.($w['message'] ?? ''));
            }
        }

        return self::SUCCESS;
    }
}

<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$docId = '7161652f-04a2-48ce-8bfe-7fb619ca7270';
$sourcePath = '/tmp/scarifier-source.docx';

$parser = app(App\Infrastructure\Docx\Ooxml\OoxmlNativeDocxParser::class);
$source = $parser->parse($sourcePath);

$dbBlocks = App\Models\DocumentBlock::where('document_id', $docId)->orderBy('sort')->get();

echo 'SOURCE blocks: '.count($source->blocks).PHP_EOL;
echo 'DB blocks: '.$dbBlocks->count().PHP_EOL.PHP_EOL;

$sourceBySort = [];
foreach ($source->blocks as $block) {
    $sourceBySort[$block->sort] = $block;
}

$dupPatterns = [];
$dialogue = [];
$calloutOnly = 0;
$canvasBlocks = 0;
$segmentDupes = 0;
$sortMismatches = [];

foreach ($dbBlocks as $db) {
    $plain = trim((string) ($db->text_translated ?: $db->text_original ?: ''));
    $orig = trim((string) ($db->text_original ?: ''));

    if (preg_match('/([\p{L}\p{N}][\p{L}\p{N}\s,\-]{2,}?)\s+\1/u', $plain)) {
        $dupPatterns[] = 'sort='.$db->sort.': '.mb_substr($plain, 0, 90);
    }
    if (preg_match('/Пользователь:|Ответ:/u', $plain)) {
        $dialogue[] = 'sort='.$db->sort.': '.mb_substr($plain, 0, 120);
    }
    if (preg_match('/^\d{1,3}$/u', $plain) && str_contains((string) $db->html, 'doc-textbox')) {
        $calloutOnly++;
    }
    if (str_contains((string) $db->html, 'doc-anchored-canvas')) {
        $canvasBlocks++;
    }

    $content = $db->content_json;
    if (is_array($content) && isset($content['children'])) {
        $texts = [];
        foreach ($content['children'] as $child) {
            if (($child['kind'] ?? '') === 'text') {
                $texts[] = (string) ($child['text'] ?? '');
            }
        }
        if (count($texts) !== count(array_unique($texts))) {
            $segmentDupes++;
        }
    }

    $src = $sourceBySort[$db->sort] ?? null;
    if ($src) {
        $srcPlain = trim((string) ($src->textOriginal ?? strip_tags((string) $src->html)));
        if ($srcPlain !== $orig) {
            $sortMismatches[] = 'sort='.$db->sort.' src="'.mb_substr($srcPlain, 0, 60).'" db="'.mb_substr($orig, 0, 60).'"';
        }
    }
}

echo '=== ISSUES IN STORED (TRANSLATED) DOCUMENT ==='.PHP_EOL;
echo 'RU/RU duplicate phrase patterns: '.count($dupPatterns).PHP_EOL;
foreach (array_slice($dupPatterns, 0, 10) as $line) {
    echo '  '.$line.PHP_EOL;
}
echo 'Dialogue hallucinations (Пользователь/Ответ): '.count($dialogue).PHP_EOL;
foreach ($dialogue as $line) {
    echo '  '.$line.PHP_EOL;
}
echo 'Standalone numeric callout blocks: '.$calloutOnly.PHP_EOL;
echo 'Anchored canvas blocks: '.$canvasBlocks.PHP_EOL;
echo 'Blocks with duplicate content_json segments: '.$segmentDupes.PHP_EOL;
echo 'Sort/plain mismatches vs fresh source parse: '.count($sortMismatches).PHP_EOL;
foreach (array_slice($sortMismatches, 0, 5) as $line) {
    echo '  '.$line.PHP_EOL;
}

echo PHP_EOL.'=== KEY BLOCKS (source vs stored) ==='.PHP_EOL;
foreach ([16, 34, 36, 137, 155] as $sort) {
    $db = $dbBlocks->firstWhere('sort', $sort);
    $src = $sourceBySort[$sort] ?? null;
    if (! $db && ! $src) {
        continue;
    }

    echo PHP_EOL.'--- sort='.$sort.' ---'.PHP_EOL;
    echo 'SOURCE orig: '.mb_substr((string) ($src?->textOriginal ?? ''), 0, 120).PHP_EOL;
    echo 'DB orig:     '.mb_substr((string) ($db?->text_original ?? ''), 0, 120).PHP_EOL;
    echo 'DB transl:   '.mb_substr((string) ($db?->text_translated ?? ''), 0, 120).PHP_EOL;

    $srcSeg = array_column($src?->meta['ooxml_segments'] ?? [], 'text');
    echo 'SOURCE segments: '.json_encode($srcSeg, JSON_UNESCAPED_UNICODE).PHP_EOL;
}

echo PHP_EOL.'=== FRESH PARSE BASELINE (current code, no translation) ==='.PHP_EOL;
$baselineDup = 0;
$baselineCallouts = 0;
foreach ($source->blocks as $block) {
    $plain = trim((string) ($block->textOriginal ?? ''));
    if (preg_match('/([\p{L}\p{N}][\p{L}\p{N}\s,\-]{2,}?)\s+\1/u', $plain)) {
        $baselineDup++;
    }
    if (preg_match('/^\d{1,3}$/u', $plain) && str_contains((string) $block->html, 'doc-textbox')) {
        $baselineCallouts++;
    }
}
echo 'Duplicate plain patterns in source parse: '.$baselineDup.PHP_EOL;
echo 'Standalone callout blocks in source parse: '.$baselineCallouts.PHP_EOL;

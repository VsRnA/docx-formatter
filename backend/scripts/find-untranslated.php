<?php

/**
 * Finds blocks where translated HTML still contains source-language text.
 *
 * Usage:
 *   php scripts/find-untranslated.php /path/to/file.docx [language_from] [language_to]
 *   php scripts/find-untranslated.php --document-id=<uuid>
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domain\Docx\Entity\ParsedBlock;
use App\Domain\Docx\ValueObject\BlockType;
use App\Infrastructure\Docx\Ooxml\OoxmlNativeDocxParser;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlHtmlSegmentAnnotator;
use App\Infrastructure\Document\Persist\BlockTranslationApplicator;
use App\Infrastructure\Document\Translation\SegmentTranslationCoordinator;
use App\Infrastructure\Document\Translation\TranslatedHtmlPatcher;
use App\Models\Document;
use App\Models\DocumentBlock;
use App\Services\Ai\MockTranslationService;

$args = array_slice($argv, 1);
$documentId = null;
$docxPath = null;
$languageFrom = 'en';
$languageTo = 'ru';

foreach ($args as $arg) {
    if (str_starts_with($arg, '--document-id=')) {
        $documentId = substr($arg, strlen('--document-id='));
    } elseif ($docxPath === null) {
        $docxPath = $arg;
    } elseif ($languageFrom === 'en' && $languageTo === 'ru' && ! str_starts_with($arg, '--')) {
        $languageFrom = $arg;
    } elseif ($languageFrom !== 'en' || $languageTo !== 'ru') {
        $languageTo = $arg;
    } else {
        $languageTo = $arg;
    }
}

if ($documentId === null && ($docxPath === null || ! is_file($docxPath))) {
    $default = dirname(__DIR__, 2).'/Revised MANUAL for Scarifier-2025.09.08.docx';
    if (is_file($default)) {
        $docxPath = $default;
    }
}

config(['services.mock.translate_enabled' => true]);

$segmentHtml = new OoxmlHtmlSegmentAnnotator;
$htmlPatcher = new TranslatedHtmlPatcher;
$translator = app(\App\Domain\Docx\Port\TranslatorPort::class);
$applicator = new BlockTranslationApplicator(
    $translator,
    new SegmentTranslationCoordinator($translator, $segmentHtml, $htmlPatcher),
    $htmlPatcher,
);

$document = new Document;
$document->language_from = $languageFrom;
$document->language_to = $languageTo;

$issues = [];

if ($documentId !== null) {
    $dbBlocks = DocumentBlock::query()->where('document_id', $documentId)->orderBy('sort')->get();
    echo 'Checking stored document '.$documentId.' ('.$dbBlocks->count()." blocks)\n\n";

    foreach ($dbBlocks as $block) {
        $html = (string) ($block->html ?? '');
        $original = trim((string) ($block->text_original ?? ''));
        $translated = trim((string) ($block->text_translated ?? ''));

        if ($original === '' || $translated === null) {
            continue;
        }

        if (containsUntranslatedSource($html, $original, $languageFrom, $languageTo)) {
            $issues[] = buildIssue($block->sort, $original, $html, $block->content_json['meta']['ooxml_segments'] ?? null);
        }
    }
} else {
    if ($docxPath === null) {
        fwrite(STDERR, "Usage: php scripts/find-untranslated.php <docx-path> [from] [to]\n");
        exit(1);
    }

    echo 'Parsing '.$docxPath."\n";
    $parser = app(OoxmlNativeDocxParser::class);
    $parsed = $parser->parse($docxPath);
    echo 'Blocks: '.count($parsed->blocks)."\n\n";

    foreach ($parsed->blocks as $block) {
        $html = (string) ($block->html ?? '');
        $original = trim((string) ($block->textOriginal ?? ''));

        if ($original === '') {
            continue;
        }

        $result = $applicator->apply(
            $document,
            new ParsedBlock(
                type: BlockType::from($block->type->value),
                sort: $block->sort,
                html: $html,
                textOriginal: $block->textOriginal,
                meta: $block->meta,
            ),
            $html,
            true,
        );

        $resultHtml = (string) ($result['html'] ?? $html);

        if (containsUntranslatedSource($resultHtml, $original, $languageFrom, $languageTo)) {
            $issues[] = buildIssue($block->sort, $original, $resultHtml, $block->meta['ooxml_segments'] ?? null);
        }
    }
}

echo '=== UNTRANSLATED BLOCKS: '.count($issues)." ===\n";
foreach (array_slice($issues, 0, 50) as $issue) {
    echo sprintf(
        "sort=%d segments=%d visible=\"%s\" original=\"%s\"\n",
        $issue['sort'],
        $issue['segment_count'],
        mb_substr($issue['visible'], 0, 100),
        mb_substr($issue['original'], 0, 100),
    );
}

if (count($issues) > 50) {
    echo '... and '.(count($issues) - 50)." more\n";
}

exit($issues === [] ? 0 : 1);

/**
 * @param  list<array<string, mixed>>|null  $segments
 * @return array{sort: int, original: string, visible: string, segment_count: int}
 */
function buildIssue(int $sort, string $original, string $html, ?array $segments): array
{
    return [
        'sort' => $sort,
        'original' => $original,
        'visible' => trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? ''),
        'segment_count' => is_array($segments) ? count($segments) : 0,
    ];
}

function containsUntranslatedSource(string $html, string $original, string $languageFrom, string $languageTo): bool
{
    $visible = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
    $originalNormalized = trim(preg_replace('/\s+/u', ' ', $original) ?? '');

    if ($originalNormalized === '' || $visible === '') {
        return false;
    }

    if (! textLooksLikeSourceLanguage($originalNormalized, $languageFrom, $languageTo)) {
        return false;
    }

    if (! textLooksLikeSourceLanguage($visible, $languageFrom, $languageTo)) {
        return false;
    }

    $words = preg_split('/\s+/u', $originalNormalized) ?: [];
    $significant = array_values(array_filter(
        $words,
        static fn (string $word): bool => mb_strlen($word) >= 4 && preg_match('/\p{L}/u', $word) === 1,
    ));

    foreach ($significant as $word) {
        if (preg_match('/\b'.preg_quote($word, '/').'\b/ui', $visible) === 1) {
            return true;
        }
    }

    return similar_text_ratio($visible, $originalNormalized) >= 0.75;
}

function textLooksLikeSourceLanguage(string $text, string $languageFrom, string $languageTo): bool
{
    $letters = preg_replace('/[^\p{L}]/u', '', $text) ?? '';
    if ($letters === '') {
        return false;
    }

    $fromRatio = scriptRatio($letters, $languageFrom);
    $toRatio = scriptRatio($letters, $languageTo);

    return $fromRatio >= 0.4 && $fromRatio > $toRatio;
}

function scriptRatio(string $letters, string $lang): float
{
    $total = mb_strlen($letters);
    if ($total === 0) {
        return 0.0;
    }

    $pattern = match (strtolower($lang)) {
        'ru' => '/\p{Cyrillic}/u',
        'en' => '/[A-Za-z]/u',
        default => '/\p{L}/u',
    };

    return preg_match_all($pattern, $letters) / $total;
}

function similar_text_ratio(string $left, string $right): float
{
    similar_text($left, $right, $percent);

    return $percent / 100;
}

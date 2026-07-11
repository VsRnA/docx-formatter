<?php

namespace Tests\Unit;

use App\Domain\Docx\Service\Support\TextRunFragmentMerger;
use PHPUnit\Framework\TestCase;

class TextRunFragmentMergerTest extends TestCase
{
    public function test_detects_full_repeat_of_accumulated_text(): void
    {
        $merger = new TextRunFragmentMerger;

        $this->assertTrue($merger->repeatsAccumulated('Иванов Иван Иванович', 'Иванов Иван Иванович'));
        $this->assertFalse($merger->repeatsAccumulated('Repeat me ', 'Repeat me'));
    }

    public function test_trims_overlapping_run_suffix(): void
    {
        $merger = new TextRunFragmentMerger;

        $suffix = $merger->nonOverlappingSuffix('E-mail: user', 'user@example.com');

        // Overlap shorter than 8 chars is ignored to avoid trimming legitimate runs.
        $this->assertSame('user@example.com', $suffix);
    }

    public function test_detects_doubled_caption_text(): void
    {
        $merger = new TextRunFragmentMerger;
        $caption = 'Рисунок 1. Детальная визуализация архитектуры сети';

        $this->assertSame($caption, $merger->dedupeDoubledText($caption.$caption));
    }

    public function test_prefers_bold_html_when_plain_matches(): void
    {
        $merger = new TextRunFragmentMerger;

        $richer = $merger->prefersRicherHtml('Caption', '<strong>Caption</strong>');

        $this->assertSame('<strong>Caption</strong>', $richer);
    }

    public function test_does_not_treat_single_letter_as_superset_of_word(): void
    {
        $merger = new TextRunFragmentMerger;

        $this->assertFalse($merger->isSuperset('а', 'втоматическое извлечение'));
        $this->assertTrue($merger->isSuperset('Рисунок', 'Рисунок 1. Подпись'));
    }
}

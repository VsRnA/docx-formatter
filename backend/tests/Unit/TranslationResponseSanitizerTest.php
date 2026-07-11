<?php

namespace Tests\Unit;

use App\Infrastructure\External\Ai\Support\TranslationResponseSanitizer;
use PHPUnit\Framework\TestCase;

class TranslationResponseSanitizerTest extends TestCase
{
    private TranslationResponseSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new TranslationResponseSanitizer;
    }

    public function test_strips_dialogue_wrappers(): void
    {
        $raw = 'Пользователь: The total length of the pipeline is 120 km. Ответ: Общая длина трубопровода составляет 120 км.';

        $this->assertSame(
            'Общая длина трубопровода составляет 120 км.',
            $this->sanitizer->stripDialogueWrappers($raw),
        );
    }

    public function test_rejects_hallucinated_short_source_response(): void
    {
        $raw = 'Пользователь: The total length of the pipeline is 120 km. Ответ: Общая длина трубопровода составляет 120 км.';

        $this->assertSame('', $this->sanitizer->sanitize($raw, 'CONTENTS'));
    }

    public function test_keeps_valid_translation(): void
    {
        $this->assertSame(
            'СОДЕРЖАНИЕ',
            $this->sanitizer->sanitize('СОДЕРЖАНИЕ', 'CONTENTS'),
        );
    }
}

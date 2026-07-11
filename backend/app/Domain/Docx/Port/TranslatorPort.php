<?php

namespace App\Domain\Docx\Port;

interface TranslatorPort
{
    public function translate(string $text, string $from = 'en', string $to = 'ru'): string;

    /**
     * @param  list<string>  $texts
     * @return list<string>
     */
    public function translateMany(array $texts, string $from = 'en', string $to = 'ru'): array;

    /**
     * @param  list<list<string>>  $chunks
     * @return list<list<string>>
     */
    public function translateManyChunks(array $chunks, string $from = 'en', string $to = 'ru'): array;
}

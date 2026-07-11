<?php

namespace App\Infrastructure\Document\Translation;

use Illuminate\Support\Facades\Cache;

final class TranslationCacheStore
{
    public function isEnabled(): bool
    {
        return (bool) config('services.translation.cache_enabled', true);
    }

    public function get(string $text, string $from, string $to): ?string
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $value = Cache::get($this->cacheKey($text, $from, $to));

        return is_string($value) ? $value : null;
    }

    public function put(string $text, string $from, string $to, string $translation): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $ttlDays = (int) config('services.translation.cache_ttl_days', 30);
        Cache::put(
            $this->cacheKey($text, $from, $to),
            $translation,
            now()->addDays(max(1, $ttlDays)),
        );
    }

    private function cacheKey(string $text, string $from, string $to): string
    {
        $model = (string) config('services.yandex_ai.translate_model', 'yandexgpt');

        return 'translation:'.hash('sha256', $from.'|'.$to.'|'.$model.'|'.self::normalizeTextKey($text));
    }

    public static function normalizeTextKey(string $text): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($text));

        return is_string($normalized) ? $normalized : trim($text);
    }
}

<?php

namespace App\Rules;

use App\Support\Constants\UploadLimits;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;

class DocxUploadFile implements ValidationRule
{
    public function __construct(
        private readonly int $maxKb,
    ) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('Загруженный объект должен быть файлом');

            return;
        }

        if (! $value->isValid()) {
            $fail('Не удалось загрузить файл');

            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());
        if ($extension !== UploadLimits::DOCX_EXTENSION) {
            $fail('Допустим только формат .docx');

            return;
        }

        $mime = strtolower((string) $value->getMimeType());
        if (! in_array($mime, UploadLimits::ALLOWED_MIMES, true)) {
            $fail('Файл .docx имеет неподдерживаемый тип содержимого');

            return;
        }

        if ($value->getSize() > $this->maxKb * 1024) {
            $maxMb = (int) config('app.max_upload_mb', 50);
            $fail("Размер файла не должен превышать {$maxMb} МБ");
        }
    }
}

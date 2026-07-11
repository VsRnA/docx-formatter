<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class ImageUploadValidator
{
    /** @var list<string> */
    private const UNSUPPORTED_EXTENSIONS = ['emf', 'wmf'];

    public static function assertSupported(UploadedFile $file): void
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (in_array($extension, self::UNSUPPORTED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'file' => ['Формат EMF/WMF не поддерживается браузером.'],
            ]);
        }
    }
}

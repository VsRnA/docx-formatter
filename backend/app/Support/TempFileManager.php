<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class TempFileManager
{
    public function createPath(string $extension = 'tmp'): string
    {
        $dir = storage_path('app/tmp/'.date('Y-m-d'));
        File::ensureDirectoryExists($dir);

        return $dir.'/'.Str::uuid().'.'.$extension;
    }

    public function cleanup(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

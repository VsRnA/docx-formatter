<?php

namespace App\Infrastructure\External\Storage;

use App\Domain\Shared\Port\FileStoragePort;
use Illuminate\Support\Facades\Storage;

class S3StorageService implements FileStoragePort
{
    public function __construct(
        private readonly string $disk = 's3',
    ) {}

    public function put(string $key, string $contents, string $mimeType): void
    {
        Storage::disk($this->disk)->put($key, $contents, [
            'ContentType' => $mimeType,
        ]);
    }

    public function putFile(string $key, string $localPath, string $mimeType): void
    {
        $stream = fopen($localPath, 'r');
        Storage::disk($this->disk)->put($key, $stream, [
            'ContentType' => $mimeType,
        ]);
        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    public function get(string $key): string
    {
        return Storage::disk($this->disk)->get($key);
    }

    public function delete(string $key): void
    {
        Storage::disk($this->disk)->delete($key);
    }

    public function temporaryUrl(string $key, int $minutes = 60): string
    {
        return Storage::disk($this->disk)->temporaryUrl($key, now()->addMinutes($minutes));
    }

    public function exists(string $key): bool
    {
        return Storage::disk($this->disk)->exists($key);
    }

    public function size(string $key): ?int
    {
        if (! $this->exists($key)) {
            return null;
        }

        return Storage::disk($this->disk)->size($key);
    }
}

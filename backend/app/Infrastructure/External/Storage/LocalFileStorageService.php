<?php

namespace App\Infrastructure\External\Storage;

use App\Domain\Shared\Port\FileStoragePort;
use Illuminate\Support\Facades\File;

/**
 * Local disk stand-in for Yandex Object Storage (dev / parse testing).
 */
class LocalFileStorageService implements FileStoragePort
{
    private string $root;

    public function __construct()
    {
        $this->root = storage_path('app/'.config('services.mock.storage_path', 'mock-cloud'));
        File::ensureDirectoryExists($this->root);
    }

    public function put(string $key, string $contents, string $mimeType): void
    {
        $path = $this->absolutePath($key);
        File::ensureDirectoryExists(dirname($path));
        file_put_contents($path, $contents);
    }

    public function putFile(string $key, string $localPath, string $mimeType): void
    {
        $this->put($key, (string) file_get_contents($localPath), $mimeType);
    }

    public function get(string $key): string
    {
        $path = $this->absolutePath($key);
        if (! is_file($path)) {
            throw new \RuntimeException('Mock storage file not found: '.$key);
        }

        return (string) file_get_contents($path);
    }

    public function delete(string $key): void
    {
        $path = $this->absolutePath($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function temporaryUrl(string $key, int $minutes = 60): string
    {
        $base = rtrim((string) config('app.url'), '/');

        return $base.'/api/v1/mock-storage?key='.rawurlencode($key);
    }

    public function exists(string $key): bool
    {
        return is_file($this->absolutePath($key));
    }

    public function size(string $key): ?int
    {
        $path = $this->absolutePath($key);
        if (! is_file($path)) {
            return null;
        }

        $size = filesize($path);

        return $size === false ? null : (int) $size;
    }

    private function absolutePath(string $key): string
    {
        $key = ltrim(str_replace(['..', '\\'], ['', '/'], $key), '/');

        return $this->root.'/'.$key;
    }
}

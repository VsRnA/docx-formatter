<?php

namespace App\Domain\Shared\Port;

interface FileStoragePort
{
    public function put(string $key, string $contents, string $mimeType): void;

    public function putFile(string $key, string $localPath, string $mimeType): void;

    public function get(string $key): string;

    public function delete(string $key): void;

    public function temporaryUrl(string $key, int $minutes = 60): string;

    public function exists(string $key): bool;

    public function size(string $key): ?int;
}

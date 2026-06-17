<?php

namespace App\DTO\Document;

use Illuminate\Http\UploadedFile;

final readonly class StoreDocumentDto
{
    public function __construct(
        public UploadedFile $file,
        public string $title,
        public bool $translate = true,
    ) {}
}

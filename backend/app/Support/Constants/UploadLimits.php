<?php

namespace App\Support\Constants;

final class UploadLimits
{
    public const DOCX_EXTENSION = 'docx';

    /** @var list<string> */
    public const ALLOWED_MIMES = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip',
        'application/x-zip-compressed',
        'application/octet-stream',
    ];
}

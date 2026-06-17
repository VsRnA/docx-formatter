<?php

namespace App\Domain\Document\ValueObject;

enum DocumentStatus: string
{
    case Uploading = 'uploading';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
    case Draft = 'draft';
    case Published = 'published';
}

<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Uploading = 'uploading';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
    case Draft = 'draft';
    case Published = 'published';
}

<?php

namespace App\Domain\Document\ValueObject;

enum TranslationStatus: string
{
    case Pending = 'pending';
    case Done = 'done';
    case Failed = 'failed';
    case Skipped = 'skipped';
}

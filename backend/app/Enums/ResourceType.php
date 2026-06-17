<?php

namespace App\Enums;

enum ResourceType: string
{
    case SourceDocx = 'source_docx';
    case Image = 'image';
    case UserUpload = 'user_upload';
}

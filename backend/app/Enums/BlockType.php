<?php

namespace App\Enums;

enum BlockType: string
{
    case Heading = 'heading';
    case Paragraph = 'paragraph';
    case List = 'list';
    case Table = 'table';
    case Image = 'image';
    case Caption = 'caption';
    case ImageText = 'image_text';
    case LinkBlock = 'link_block';
    case HtmlRaw = 'html_raw';
    case Formula = 'formula';
}

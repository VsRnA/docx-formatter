<?php

namespace App\Infrastructure\Docx\Ooxml;

final class OoxmlNamespaces
{
    public const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    public const R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    public const WP = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';

    public const A = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    public const PIC = 'http://schemas.openxmlformats.org/drawingml/2006/picture';

    public const M = 'http://schemas.openxmlformats.org/officeDocument/2006/math';

    /** @return array<string, string> */
    public static function xpathMap(): array
    {
        return [
            'w' => self::W,
            'r' => self::R,
            'wp' => self::WP,
            'a' => self::A,
            'pic' => self::PIC,
            'm' => self::M,
        ];
    }
}

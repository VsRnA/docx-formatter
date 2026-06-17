<?php

namespace App\Domain\Document\ValueObject;

final readonly class ProcessingStage
{
    public function __construct(public string $value) {}

    public static function queued(): self
    {
        return new self('queued');
    }

    public static function download(): self
    {
        return new self('download');
    }

    public static function parse(): self
    {
        return new self('parse');
    }

    public static function validate(): self
    {
        return new self('validate');
    }

    public static function normalize(): self
    {
        return new self('normalize');
    }

    public static function writeDocx(): self
    {
        return new self('write_docx');
    }

    public static function buildHtml(): self
    {
        return new self('build_html');
    }

    public static function completed(): self
    {
        return new self('completed');
    }

    public static function failed(): self
    {
        return new self('failed');
    }
}

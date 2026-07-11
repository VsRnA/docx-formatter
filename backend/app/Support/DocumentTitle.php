<?php

namespace App\Support;

use App\Domain\Document\Entity\Document as DomainDocument;
use App\Models\Document;

final class DocumentTitle
{
    public static function fromUploadedFile(string $filename): string
    {
        $name = self::stripExtension(trim($filename));

        return $name !== '' ? $name : 'Документ';
    }

    public static function display(Document $document): string
    {
        return self::displayFromParts(
            trim((string) $document->title),
            $document->meta_json['original_filename'] ?? null,
        );
    }

    public static function displayFromDomain(DomainDocument $document): string
    {
        return self::displayFromParts(
            trim($document->title()),
            $document->meta()->get('original_filename'),
        );
    }

    private static function displayFromParts(string $title, mixed $originalFilename): string
    {
        $original = $originalFilename;

        if ($title !== '' && self::isGeneratedTitle($title)) {
            if (is_string($original) && $original !== '') {
                return self::fromUploadedFile($original);
            }

            return 'Документ';
        }

        if ($title !== '') {
            return self::stripExtension($title) ?: 'Документ';
        }

        if (is_string($original) && $original !== '') {
            return self::fromUploadedFile($original);
        }

        return 'Документ';
    }

    public static function isGeneratedTitle(string $title): bool
    {
        $bare = self::stripExtension(trim($title));

        if ($bare === '' || strcasecmp($bare, 'source') === 0) {
            return true;
        }

        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $bare,
        );
    }

    public static function stripExtension(string $name): string
    {
        return preg_replace('/\.docx$/i', '', trim($name)) ?? trim($name);
    }
}

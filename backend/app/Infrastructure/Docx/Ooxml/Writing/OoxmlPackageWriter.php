<?php

namespace App\Infrastructure\Docx\Ooxml\Writing;

use DOMDocument;
use RuntimeException;
use ZipArchive;

final class OoxmlPackageWriter
{
    public function saveWithDocumentXml(string $sourcePath, DOMDocument $document, string $outputPath): void
    {
        $source = new ZipArchive;
        if ($source->open($sourcePath) !== true) {
            throw new RuntimeException('Unable to open source DOCX: '.$sourcePath);
        }

        $destination = new ZipArchive;
        if ($destination->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $source->close();
            throw new RuntimeException('Unable to create output DOCX: '.$outputPath);
        }

        $documentXml = $document->saveXML();
        if ($documentXml === false) {
            $source->close();
            $destination->close();
            throw new RuntimeException('Unable to serialize word/document.xml');
        }

        for ($i = 0; $i < $source->numFiles; $i++) {
            $entry = $source->getNameIndex($i);
            if ($entry === false || $entry === 'word/document.xml') {
                continue;
            }

            $contents = $source->getFromIndex($i);
            if ($contents === false) {
                continue;
            }

            $destination->addFromString($entry, $contents);
        }

        $destination->addFromString('word/document.xml', $documentXml);
        $destination->close();
        $source->close();
    }
}

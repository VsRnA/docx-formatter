<?php

namespace App\Domain\Document\Exception;

use App\Domain\Document\ValueObject\DocumentId;

final class DocumentNotFound extends \RuntimeException
{
    public static function withId(DocumentId $id): self
    {
        return new self('Document not found: '.$id->value);
    }
}

<?php

namespace App\Domain\Document\Exception;

use RuntimeException;

final class BlockNotFound extends RuntimeException
{
    public static function withId(string $id): self
    {
        return new self("Document block not found: {$id}");
    }
}

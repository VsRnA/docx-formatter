<?php

namespace App\Domain\Document\Query;

interface DocumentListQueryPort
{
    public function paginate(int $perPage = 20): PaginatedDocuments;
}

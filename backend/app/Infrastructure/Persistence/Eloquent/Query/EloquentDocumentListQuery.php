<?php

namespace App\Infrastructure\Persistence\Eloquent\Query;

use App\Domain\Document\Query\DocumentListQueryPort;
use App\Domain\Document\Query\PaginatedDocuments;
use App\Models\Document;

final class EloquentDocumentListQuery implements DocumentListQueryPort
{
    public function paginate(int $perPage = 20): PaginatedDocuments
    {
        $paginator = Document::query()->withCount('revisions')->latest()->paginate($perPage);

        return new PaginatedDocuments(
            items: $paginator->items(),
            currentPage: $paginator->currentPage(),
            lastPage: $paginator->lastPage(),
            total: $paginator->total(),
            perPage: $paginator->perPage(),
        );
    }
}

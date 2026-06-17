<?php

namespace App\Infrastructure\Persistence\Eloquent\Query;

use App\Domain\Document\Query\DocumentListQueryPort;
use App\Models\Document;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentDocumentListQuery implements DocumentListQueryPort
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return Document::query()->latest()->paginate($perPage);
    }
}

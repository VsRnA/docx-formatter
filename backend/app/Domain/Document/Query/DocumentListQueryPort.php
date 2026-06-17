<?php

namespace App\Domain\Document\Query;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DocumentListQueryPort
{
    public function paginate(int $perPage = 20): LengthAwarePaginator;
}

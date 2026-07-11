<?php

namespace App\Domain\Document\Query;

final class PaginatedDocuments
{
    /**
     * @param  list<mixed>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $currentPage,
        public readonly int $lastPage,
        public readonly int $total,
        public readonly int $perPage,
    ) {}
}

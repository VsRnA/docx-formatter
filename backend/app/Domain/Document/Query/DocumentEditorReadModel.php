<?php

namespace App\Domain\Document\Query;

use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\DocumentResource;
use Illuminate\Support\Collection;

final class DocumentEditorReadModel
{
    /**
     * @param  Collection<int, DocumentBlock>  $blocks
     * @param  Collection<int, DocumentResource>  $resources
     */
    public function __construct(
        public readonly Document $document,
        public readonly Collection $blocks,
        public readonly Collection $resources,
    ) {}
}

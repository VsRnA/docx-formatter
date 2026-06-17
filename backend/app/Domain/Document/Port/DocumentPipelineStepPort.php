<?php

namespace App\Domain\Document\Port;

use App\Domain\Document\Entity\Document;

interface DocumentPipelineStepPort
{
    public function run(Document $document, object $state): Document;
}

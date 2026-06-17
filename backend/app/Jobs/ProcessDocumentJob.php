<?php

namespace App\Jobs;

use App\Application\Document\Command\ProcessDocument\ProcessDocumentHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $documentId,
    ) {}

    public function handle(ProcessDocumentHandler $handler): void
    {
        $handler->execute($this->documentId);
    }
}

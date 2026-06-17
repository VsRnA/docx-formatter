<?php

namespace App\Console\Commands;

use App\Application\Document\Command\ReprocessDocument\ReprocessDocumentHandler;
use App\Enums\DocumentStatus;
use App\Models\Document;
use Illuminate\Console\Command;

class ReprocessStuckDocumentsCommand extends Command
{
    protected $signature = 'documents:reprocess-stuck';

    protected $description = 'Re-dispatch processing jobs for documents stuck in queued/processing state';

    public function handle(ReprocessDocumentHandler $reprocess): int
    {
        $stuck = Document::query()
            ->where('status', DocumentStatus::Processing)
            ->where(function ($query): void {
                $query->where('processing_stage', 'queued')->orWhereNull('processing_stage');
            })
            ->pluck('id');

        if ($stuck->isEmpty()) {
            $this->info('No stuck documents.');

            return self::SUCCESS;
        }

        foreach ($stuck as $documentId) {
            $reprocess->execute((string) $documentId);
            $this->line("Requeued: {$documentId}");
        }

        $this->info("Requeued {$stuck->count()} document(s).");

        return self::SUCCESS;
    }
}

<?php

namespace App\Infrastructure\Document\Revision;

use App\Domain\Document\Entity\Document;
use App\Domain\Document\Entity\DocumentBlock;
use App\Domain\Document\Port\HtmlBuilderPort;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;
use App\Models\Document as DocumentModel;
use App\Models\DocumentRevision;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class DocumentRevisionService
{
    private const CHECKPOINT_INTERVAL_MINUTES = 10;

    private const MAX_REVISIONS_PER_DOCUMENT = 50;

    public function __construct(
        private readonly DocumentRepositoryInterface $documents,
        private readonly HtmlBuilderPort $htmlBuilder,
    ) {}

    /**
     * @return list<DocumentRevision>
     */
    public function list(string $documentId, int $limit = 50): array
    {
        return DocumentRevision::query()
            ->where('document_id', $documentId)
            ->orderByDesc('created_at')
            ->limit(max(1, $limit))
            ->get()
            ->all();
    }

    public function find(string $documentId, string $revisionId): DocumentRevision
    {
        return DocumentRevision::query()
            ->where('document_id', $documentId)
            ->where('id', $revisionId)
            ->firstOrFail();
    }

    public function createManualSnapshot(string $documentId, ?string $label = null): DocumentRevision
    {
        $document = $this->documents->find(new DocumentId($documentId));

        return $this->persistSnapshot($document, 'manual', $label);
    }

    public function createPreRestoreSnapshot(string $documentId): DocumentRevision
    {
        $document = $this->documents->find(new DocumentId($documentId));

        return $this->persistSnapshot($document, 'pre_restore');
    }

    public function maybeCreateAutosaveCheckpoint(string $documentId): ?DocumentRevision
    {
        $latest = DocumentRevision::query()
            ->where('document_id', $documentId)
            ->where('trigger', 'autosave_checkpoint')
            ->orderByDesc('created_at')
            ->first();

        if ($latest !== null && $latest->created_at instanceof Carbon) {
            $elapsedMinutes = $latest->created_at->diffInMinutes(now());
            if ($elapsedMinutes < self::CHECKPOINT_INTERVAL_MINUTES) {
                return null;
            }
        }

        $document = $this->documents->find(new DocumentId($documentId));

        return $this->persistSnapshot($document, 'autosave_checkpoint');
    }

    public function restore(string $documentId, string $revisionId): DocumentModel
    {
        $revision = $this->find($documentId, $revisionId);
        $this->createPreRestoreSnapshot($documentId);

        $document = $this->documents->find(new DocumentId($documentId));
        $document->replaceBlocks($this->blocksFromSnapshot($revision->blocks_snapshot ?? []));
        $htmlDraft = $revision->html_draft_snapshot;
        if (! is_string($htmlDraft) || $htmlDraft === '') {
            $htmlDraft = $this->htmlBuilder->buildFromDocument($document);
        }
        $document->setHtmlDraft($htmlDraft);
        $this->documents->save($document);

        return DocumentModel::query()->findOrFail($documentId);
    }

    /**
     * @param  list<array<string, mixed>>  $snapshot
     * @return list<DocumentBlock>
     */
    private function blocksFromSnapshot(array $snapshot): array
    {
        return array_map(
            static fn (array $item) => DocumentBlock::fromArray($item),
            $snapshot,
        );
    }

    private function persistSnapshot(Document $document, string $trigger, ?string $label = null): DocumentRevision
    {
        $revision = DocumentRevision::query()->create([
            'id' => (string) Str::uuid(),
            'document_id' => $document->id()->value,
            'trigger' => $trigger,
            'label' => $label,
            'blocks_snapshot' => array_map(
                static fn (DocumentBlock $block) => $block->toArray(),
                $document->blocks(),
            ),
            'html_draft_snapshot' => $document->htmlDraft(),
            'created_at' => now(),
        ]);

        $this->pruneOldRevisions($document->id()->value);

        return $revision;
    }

    private function pruneOldRevisions(string $documentId): void
    {
        $idsToDelete = DocumentRevision::query()
            ->where('document_id', $documentId)
            ->orderByDesc('created_at')
            ->skip(self::MAX_REVISIONS_PER_DOCUMENT)
            ->pluck('id')
            ->all();

        if ($idsToDelete !== []) {
            DocumentRevision::query()->whereIn('id', $idsToDelete)->delete();
        }
    }
}

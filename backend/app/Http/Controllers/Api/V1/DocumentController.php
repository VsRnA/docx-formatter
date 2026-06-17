<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Document\Command\PublishDocument\PublishDocumentHandler;
use App\Application\Document\Command\ReprocessDocument\ReprocessDocumentHandler;
use App\Application\Document\Command\SaveDocumentDraft\SaveDocumentDraftHandler;
use App\Application\Document\Command\StoreDocument\StoreDocumentHandler;
use App\Application\Document\Query\ExportDocumentHtml\ExportDocumentHtmlHandler;
use App\Application\Document\Query\GetDocumentEditor\GetDocumentEditorHandler;
use App\DTO\Document\SaveDocumentDraftDto;
use App\DTO\Document\SaveDraftBlockDto;
use App\DTO\Document\StoreDocumentDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveDocumentDraftRequest;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Resources\DocumentBlockResource;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\DocumentResourceItemResource;
use App\Models\Document;
use App\Domain\Document\Query\DocumentListQueryPort;
use App\Domain\Shared\Port\FileStoragePort;
use App\Support\DocumentTitle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentListQueryPort $documents,
        private readonly StoreDocumentHandler $storeDocument,
        private readonly GetDocumentEditorHandler $getEditor,
        private readonly SaveDocumentDraftHandler $saveDraft,
        private readonly PublishDocumentHandler $publishDocument,
        private readonly ExportDocumentHtmlHandler $exportHtml,
        private readonly ReprocessDocumentHandler $reprocessDocument,
        private readonly FileStoragePort $storage,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return DocumentResource::collection($this->documents->paginate());
    }

    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $dto = new StoreDocumentDto(
            file: $file,
            title: $request->string('title')->toString() ?: $file->getClientOriginalName(),
            translate: filter_var($request->input('translate', true), FILTER_VALIDATE_BOOLEAN),
        );

        $document = $this->storeDocument->execute($dto);

        return (new DocumentResource($document))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Document $document): DocumentResource
    {
        return new DocumentResource($document);
    }

    public function status(Document $document): JsonResponse
    {
        $meta = $document->meta_json ?? [];

        return response()->json([
            'id' => $document->id,
            'status' => $document->status?->value ?? $document->status,
            'processing_stage' => $document->processing_stage,
            'processing_error' => $document->processing_error,
            'parse_coverage' => $meta['parse_coverage'] ?? null,
            'parse_warnings_count' => is_array($meta['parse_warnings'] ?? null)
                ? count($meta['parse_warnings'])
                : 0,
            'parse_warnings' => $this->summarizeWarnings($meta['parse_warnings'] ?? null),
            'has_translated_docx' => $this->hasTranslatedDocx($document),
        ]);
    }

    public function downloadTranslated(Document $document): \Symfony\Component\HttpFoundation\Response
    {
        $key = $document->meta_json['translated_file_key'] ?? null;
        if (! is_string($key) || $key === '' || ! $this->storage->exists($key)) {
            abort(404, 'Translated DOCX is not available for this document.');
        }

        $filename = preg_replace('/[^\p{L}\p{N}\-_]+/u', '-', DocumentTitle::display($document)) ?: 'document';

        return response($this->storage->get($key), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="'.$filename.'-translated.docx"',
        ]);
    }

    /**
     * @return list<array{type: string, count: int}>
     */
    private function summarizeWarnings(mixed $warnings): array
    {
        if (! is_array($warnings)) {
            return [];
        }

        $grouped = [];
        foreach ($warnings as $warning) {
            $type = is_array($warning) ? (string) ($warning['type'] ?? 'unknown') : 'unknown';
            $grouped[$type] = ($grouped[$type] ?? 0) + 1;
        }

        $summary = [];
        foreach ($grouped as $type => $count) {
            $summary[] = ['type' => $type, 'count' => $count];
        }

        return $summary;
    }

    private function hasTranslatedDocx(Document $document): bool
    {
        $key = $document->meta_json['translated_file_key'] ?? null;

        return is_string($key) && $key !== '';
    }

    public function reprocess(Document $document): DocumentResource
    {
        $updated = $this->reprocessDocument->execute($document->id);

        return new DocumentResource($updated);
    }

    public function editor(Document $document): JsonResponse
    {
        $payload = $this->getEditor->execute($document->id);

        return response()->json([
            'document' => new DocumentResource($payload->document),
            'blocks' => DocumentBlockResource::collection($payload->blocks),
            'resources' => DocumentResourceItemResource::collection($payload->resources),
        ]);
    }

    public function update(SaveDocumentDraftRequest $request, Document $document): DocumentResource
    {
        $blocks = collect($request->validated('blocks'))->map(fn (array $b) => new SaveDraftBlockDto(
            id: $b['id'],
            type: $b['type'],
            sort: (int) $b['sort'],
            html: $b['html'] ?? null,
            styles: $b['styles'] ?? null,
            meta: $b['meta'] ?? null,
            assets: $b['assets'] ?? null,
        ))->all();

        $updated = $this->saveDraft->execute(new SaveDocumentDraftDto($document->id, $blocks));

        return new DocumentResource($updated);
    }

    public function publish(Document $document): DocumentResource
    {
        return new DocumentResource($this->publishDocument->execute($document->id));
    }

    public function exportHtml(Document $document): \Symfony\Component\HttpFoundation\Response
    {
        $html = $this->exportHtml->execute($document->id);
        $filename = preg_replace('/[^\p{L}\p{N}\-_]+/u', '-', DocumentTitle::display($document)) ?: 'document';

        return response("\xEF\xBB\xBF".$html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.html"',
        ]);
    }
}

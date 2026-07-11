<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Document\Command\CreateDocumentRevision\CreateDocumentRevisionHandler;
use App\Application\Document\Command\RestoreDocumentRevision\RestoreDocumentRevisionHandler;
use App\Application\Document\Query\GetDocumentRevision\GetDocumentRevisionHandler;
use App\Application\Document\Query\ListDocumentRevisions\ListDocumentRevisionsHandler;
use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\DocumentRevisionResource;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentRevisionController extends Controller
{
    public function __construct(
        private readonly ListDocumentRevisionsHandler $listRevisions,
        private readonly GetDocumentRevisionHandler $getRevision,
        private readonly CreateDocumentRevisionHandler $createRevision,
        private readonly RestoreDocumentRevisionHandler $restoreRevision,
    ) {}

    public function index(Document $document): JsonResponse
    {
        return response()->json([
            'data' => DocumentRevisionResource::collection(
                $this->listRevisions->execute($document->id),
            ),
        ]);
    }

    public function show(Document $document, string $revision): DocumentRevisionResource
    {
        return new DocumentRevisionResource(
            $this->getRevision->execute($document->id, $revision),
        );
    }

    public function store(Request $request, Document $document): DocumentRevisionResource
    {
        $label = $request->string('label')->toString() ?: null;

        return new DocumentRevisionResource(
            $this->createRevision->execute($document->id, $label),
        );
    }

    public function restore(Document $document, string $revision): DocumentResource
    {
        return new DocumentResource(
            $this->restoreRevision->execute($document->id, $revision),
        );
    }
}

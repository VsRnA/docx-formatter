<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Document\Query\GetPublicDocument\GetPublicDocumentHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PublicDocumentController extends Controller
{
    public function __construct(
        private readonly GetPublicDocumentHandler $getPublicDocument,
    ) {}

    public function show(string $slug): JsonResponse
    {
        $payload = $this->getPublicDocument->execute($slug);

        if ($payload === null) {
            abort(404, 'Document not found');
        }

        return response()->json(['data' => $payload]);
    }
}

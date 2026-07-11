<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Ai\Command\ReworkText\ReworkTextHandler;
use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function __construct(
        private readonly ReworkTextHandler $reworkText,
    ) {}

    public function rework(Request $request, Document $document): JsonResponse
    {
        $validated = $request->validate([
            'block_id' => ['nullable', 'string', 'uuid'],
            'text' => ['required', 'string', 'max:20000'],
            'prompt' => ['required', 'string', 'max:4000'],
        ]);

        $text = $this->reworkText->execute($validated['text'], $validated['prompt']);

        return response()->json([
            'text' => $text,
            'block_id' => $validated['block_id'] ?? null,
            'document_id' => $document->id,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Block\Command\CreateBlock\CreateBlockHandler;
use App\Application\Block\Command\DeleteBlock\DeleteBlockHandler;
use App\Application\Block\Command\DuplicateBlock\DuplicateBlockHandler;
use App\Application\Block\Command\ReorderBlocks\ReorderBlocksHandler;
use App\Application\Block\Command\UpdateBlock\UpdateBlockHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateBlockRequest;
use App\Http\Requests\ReorderBlocksRequest;
use App\Http\Requests\UpdateBlockRequest;
use App\Http\Resources\DocumentBlockResource;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\DocumentBlock;
use Illuminate\Http\JsonResponse;

class BlockController extends Controller
{
    public function __construct(
        private readonly CreateBlockHandler $createBlock,
        private readonly UpdateBlockHandler $updateBlock,
        private readonly DeleteBlockHandler $deleteBlock,
        private readonly DuplicateBlockHandler $duplicateBlock,
        private readonly ReorderBlocksHandler $reorderBlocks,
    ) {}

    public function store(CreateBlockRequest $request, Document $document): DocumentBlockResource
    {
        $block = $this->createBlock->execute($document->id, $request->validated());

        return new DocumentBlockResource($block);
    }

    public function update(UpdateBlockRequest $request, Document $document, DocumentBlock $block): DocumentBlockResource
    {
        return new DocumentBlockResource(
            $this->updateBlock->execute($document->id, $block->id, $request->validated())
        );
    }

    public function destroy(Document $document, DocumentBlock $block): JsonResponse
    {
        $this->deleteBlock->execute($document->id, $block->id);

        return response()->json(null, 204);
    }

    public function duplicate(Document $document, DocumentBlock $block): DocumentBlockResource
    {
        return new DocumentBlockResource(
            $this->duplicateBlock->execute($document->id, $block->id)
        );
    }

    public function reorder(ReorderBlocksRequest $request, Document $document): DocumentResource
    {
        $document = $this->reorderBlocks->execute(
            $document->id,
            $request->validated('ordered_ids')
        );

        return new DocumentResource($document);
    }
}

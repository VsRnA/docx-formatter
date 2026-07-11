<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Image\Command\DeleteImage\DeleteImageHandler;
use App\Application\Image\Command\ReplaceImage\ReplaceImageHandler;
use App\Application\Image\Command\UploadImage\UploadImageHandler;
use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResourceItemResource;
use App\Models\Document;
use App\Models\DocumentResource;
use App\Support\ImageUploadValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImageController extends Controller
{
    public function __construct(
        private readonly UploadImageHandler $uploadImage,
        private readonly ReplaceImageHandler $replaceImage,
        private readonly DeleteImageHandler $deleteImage,
    ) {}

    public function store(Request $request, Document $document): DocumentResourceItemResource
    {
        $request->validate([
            'file' => ['required', 'file', 'image', 'max:10240'],
        ]);

        $file = $request->file('file');
        ImageUploadValidator::assertSupported($file);

        $resource = $this->uploadImage->execute($document->id, $file);

        return new DocumentResourceItemResource($resource);
    }

    public function update(Request $request, Document $document, DocumentResource $resource): DocumentResourceItemResource
    {
        $request->validate([
            'file' => ['required', 'file', 'image', 'max:10240'],
        ]);

        $file = $request->file('file');
        ImageUploadValidator::assertSupported($file);

        return new DocumentResourceItemResource(
            $this->replaceImage->execute($resource, $file)
        );
    }

    public function destroy(Document $document, DocumentResource $resource): JsonResponse
    {
        $this->deleteImage->execute($resource);

        return response()->json(null, 204);
    }
}

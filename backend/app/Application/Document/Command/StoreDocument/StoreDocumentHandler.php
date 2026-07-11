<?php

namespace App\Application\Document\Command\StoreDocument;

use App\Domain\Document\Entity\Document;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\Repository\ResourceRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Document\ValueObject\DocumentMeta;
use App\Domain\Document\ValueObject\DocumentStatus;
use App\Domain\Shared\Port\FileStoragePort;
use App\DTO\Document\StoreDocumentDto;
use App\Enums\ResourceType;
use App\Jobs\ProcessDocumentJob;
use App\Models\Document as DocumentModel;
use App\Support\DocumentTitle;
use Illuminate\Support\Str;

final class StoreDocumentHandler
{
    public function __construct(
        private readonly FileStoragePort $storage,
        private readonly DocumentRepositoryInterface $documents,
        private readonly ResourceRepositoryInterface $resources,
    ) {}

    public function execute(StoreDocumentDto $dto): DocumentModel
    {
        $documentId = new DocumentId((string) Str::uuid());
        $extension = $dto->file->getClientOriginalExtension() ?: 'docx';
        $storageKey = sprintf('documents/%s/source.%s', $documentId->value, $extension);

        $this->storage->putFile(
            $storageKey,
            $dto->file->getRealPath() ?: $dto->file->path(),
            $dto->file->getMimeType() ?: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );

        $originalFilename = $dto->file->getClientOriginalName();

        $domain = new Document(
            id: $documentId,
            title: DocumentTitle::fromUploadedFile($dto->title ?: $originalFilename),
            slug: null,
            sourceFileKey: $storageKey,
            languageFrom: $dto->translate ? 'en' : 'ru',
            languageTo: 'ru',
            status: DocumentStatus::Processing,
            processingStage: 'queued',
            processingError: null,
            htmlDraft: null,
            htmlPublished: null,
            meta: new DocumentMeta([
                'translate' => $dto->translate,
                'original_filename' => $originalFilename,
            ]),
        );

        $this->documents->insert($domain);

        $eloquent = DocumentModel::query()->findOrFail($documentId->value);
        $this->resources->create($eloquent, [
            'type' => ResourceType::SourceDocx,
            'storage_key' => $storageKey,
            'mime_type' => $dto->file->getMimeType(),
            'size' => $dto->file->getSize(),
        ]);

        ProcessDocumentJob::dispatch($documentId->value);

        return $eloquent;
    }
}

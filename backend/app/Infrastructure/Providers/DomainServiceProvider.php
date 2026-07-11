<?php

namespace App\Infrastructure\Providers;

use App\Application\Document\Command\ProcessDocument\ProcessDocumentHandler;
use App\Application\Document\Processing\DocumentProcessingPipeline;
use App\Application\Document\Processing\Steps\AiNormalizeBlocksStep;
use App\Application\Document\Processing\Steps\BuildHtmlDraftStep;
use App\Application\Document\Processing\Steps\DownloadSourceStep;
use App\Application\Document\Processing\Steps\ParseAndPersistBlocksStep;
use App\Application\Document\Processing\Steps\WriteTranslatedDocxStep;
use App\Domain\Document\Port\HtmlBuilderPort;
use App\Domain\Document\Port\HtmlRendererPort;
use App\Domain\Document\Port\HtmlSanitizerPort;
use App\Domain\Document\Query\DocumentEditorQueryPort;
use App\Domain\Document\Query\DocumentListQueryPort;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\Repository\ResourceRepositoryInterface;
use App\Domain\Docx\Port\BlockNormalizerPort;
use App\Domain\Docx\Port\DocxParserPort;
use App\Domain\Docx\Port\TranslatorPort;
use App\Domain\Shared\Port\CompletionPort;
use App\Domain\Shared\Port\FileStoragePort;
use App\Infrastructure\Document\DomainHtmlBuilderAdapter;
use App\Infrastructure\Document\HtmlSanitizerService;
use App\Infrastructure\Document\Revision\DocumentRevisionService;
use App\Infrastructure\Docx\Adapter\NativeDocxParserAdapter;
use App\Infrastructure\Docx\Ooxml\Ir\HtmlRenderer;
use App\Infrastructure\Docx\Ooxml\OoxmlDocxWriter;
use App\Infrastructure\Docx\Ooxml\Styles\OoxmlNumberingResolver;
use App\Infrastructure\Docx\Ooxml\Styles\OoxmlStyleResolver;
use App\Infrastructure\External\Ai\MockBlockNormalizerService;
use App\Infrastructure\External\Ai\MockTranslationService;
use App\Infrastructure\External\Ai\YandexBlockNormalizerService;
use App\Infrastructure\External\Ai\YandexCompletionClient;
use App\Infrastructure\External\Ai\YandexTranslationService;
use App\Infrastructure\External\Storage\LocalFileStorageService;
use App\Infrastructure\External\Storage\S3StorageService;
use App\Infrastructure\Persistence\Eloquent\Mapper\BlockMapper;
use App\Infrastructure\Persistence\Eloquent\Mapper\DocumentMapper;
use App\Infrastructure\Persistence\Eloquent\Query\EloquentDocumentEditorQuery;
use App\Infrastructure\Persistence\Eloquent\Query\EloquentDocumentListQuery;
use App\Infrastructure\Persistence\Eloquent\Repository\EloquentDocumentRepository;
use App\Infrastructure\Persistence\Eloquent\Repository\EloquentResourceRepository;
use App\Support\TempFileManager;
use Illuminate\Support\ServiceProvider;

final class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $mockStorage = config('services.integrations.mock_storage', false);
        $mockTranslation = config('services.integrations.mock_translation', false);
        $mockNormalizer = config('services.integrations.mock_normalizer', $mockTranslation);

        $this->app->bind(FileStoragePort::class, $mockStorage
            ? LocalFileStorageService::class
            : S3StorageService::class);

        $this->app->bind(TranslatorPort::class, $mockTranslation
            ? MockTranslationService::class
            : YandexTranslationService::class);
        $this->app->bind(BlockNormalizerPort::class, $mockNormalizer
            ? MockBlockNormalizerService::class
            : YandexBlockNormalizerService::class);

        $this->app->bind(CompletionPort::class, YandexCompletionClient::class);

        $this->app->bind(HtmlRendererPort::class, HtmlRenderer::class);
        $this->app->bind(HtmlBuilderPort::class, DomainHtmlBuilderAdapter::class);
        $this->app->bind(HtmlSanitizerPort::class, HtmlSanitizerService::class);
        $this->app->bind(DocxParserPort::class, NativeDocxParserAdapter::class);

        $this->app->singleton(OoxmlStyleResolver::class);
        $this->app->singleton(OoxmlNumberingResolver::class);
        $this->app->singleton(OoxmlDocxWriter::class);

        $this->app->singleton(BlockMapper::class);
        $this->app->singleton(DocumentMapper::class);
        $this->app->bind(DocumentRepositoryInterface::class, EloquentDocumentRepository::class);
        $this->app->bind(ResourceRepositoryInterface::class, EloquentResourceRepository::class);
        $this->app->bind(DocumentEditorQueryPort::class, EloquentDocumentEditorQuery::class);
        $this->app->bind(DocumentListQueryPort::class, EloquentDocumentListQuery::class);
        $this->app->singleton(DocumentRevisionService::class);

        $this->app->singleton(DocumentProcessingPipeline::class, function ($app) {
            $steps = [
                $app->make(DownloadSourceStep::class),
                $app->make(ParseAndPersistBlocksStep::class),
                $app->make(AiNormalizeBlocksStep::class),
                $app->make(BuildHtmlDraftStep::class),
            ];

            if (config('services.docx.write_translated_docx', false)) {
                $steps[] = $app->make(WriteTranslatedDocxStep::class);
            }

            return new DocumentProcessingPipeline(
                $app->make(DocumentRepositoryInterface::class),
                $app->make(TempFileManager::class),
                $steps,
            );
        });

        $this->app->bind(ProcessDocumentHandler::class);
    }
}

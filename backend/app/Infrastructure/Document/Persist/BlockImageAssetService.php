<?php

namespace App\Infrastructure\Document\Persist;

use App\Domain\Document\Repository\ResourceRepositoryInterface;
use App\Domain\Docx\Entity\ParsedBlock;
use App\Domain\Shared\Port\FileStoragePort;
use App\Enums\ResourceType;
use App\Infrastructure\Docx\Ooxml\Parsing\OoxmlImageBlockFactory;
use App\Models\Document;
use App\Support\TempFileManager;

class BlockImageAssetService
{
    public function __construct(
        private readonly FileStoragePort $storage,
        private readonly ResourceRepositoryInterface $resources,
        private readonly TempFileManager $tempFiles,
        private readonly OoxmlImageBlockFactory $imageFigures,
    ) {}

    /**
     * @return array{html: ?string, assets: array<string, mixed>}
     */
    public function uploadIfNeeded(Document $document, ParsedBlock $block): array
    {
        $assets = $block->assets ?? [];

        if (! $block->localImagePath || ! is_file($block->localImagePath)) {
            return ['html' => $block->html, 'assets' => $assets];
        }

        $attributes = is_array($block->meta['image'] ?? null) ? $block->meta['image'] : [];
        $uploaded = $this->uploadLocalFile($document, $block->localImagePath);

        $assets['resource_id'] = $uploaded['resource_id'];
        $assets['url'] = $uploaded['url'];
        $html = $this->imageFigures->buildUploadedFigure($uploaded['url'], $attributes);

        return ['html' => $html, 'assets' => $assets];
    }

    /**
     * @param  list<array{marker?: string, relationship_id?: string, local_path?: ?string, attributes?: array{alt?: ?string, width_px?: ?int, height_px?: ?int}}>  $pendingImages
     * @return array{html: string, assets: array<string, mixed>}
     */
    public function resolvePendingImages(Document $document, string $html, array $pendingImages): array
    {
        $assets = ['table_images' => []];
        $uploadedByRelationship = [];

        foreach ($pendingImages as $pending) {
            $marker = (string) ($pending['marker'] ?? $pending['relationship_id'] ?? '');
            if ($marker === '') {
                continue;
            }

            $attributes = is_array($pending['attributes'] ?? null) ? $pending['attributes'] : [];
            $relationshipId = (string) ($pending['relationship_id'] ?? $marker);
            $localPath = $pending['local_path'] ?? null;
            if (! is_string($localPath) || ! is_file($localPath)) {
                continue;
            }

            if (! isset($uploadedByRelationship[$relationshipId])) {
                $uploadedByRelationship[$relationshipId] = $this->uploadLocalFile($document, $localPath);
            }

            $uploaded = $uploadedByRelationship[$relationshipId];
            $isUnplaced = (bool) ($attributes['unplaced'] ?? false);

            if (! $isUnplaced) {
                $figure = $this->imageFigures->buildUploadedFigure($uploaded['url'], $attributes);
                $html = $this->replacePendingFigure($html, $marker, $figure);

                $assets['table_images'][] = [
                    'marker' => $marker,
                    'relationship_id' => $relationshipId,
                    'resource_id' => $uploaded['resource_id'],
                    'url' => $uploaded['url'],
                ];
            }
        }

        return ['html' => $html, 'assets' => $assets];
    }

    /**
     * @return array{resource_id: string, url: string}
     */
    private function uploadLocalFile(Document $document, string $localPath): array
    {
        $extension = strtolower(pathinfo($localPath, PATHINFO_EXTENSION) ?: 'png');
        $mimeType = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'emf', 'wmf' => 'image/x-emf',
            default => 'image/png',
        };

        $imageKey = sprintf('documents/%s/images/%s.%s', $document->id, uniqid(), $extension);
        $this->storage->putFile($imageKey, $localPath, $mimeType);

        $resource = $this->resources->create($document, [
            'type' => ResourceType::Image,
            'storage_key' => $imageKey,
            'url' => $this->storage->temporaryUrl($imageKey),
            'mime_type' => $mimeType,
            'size' => filesize($localPath),
        ]);

        $this->tempFiles->cleanup($localPath);

        return [
            'resource_id' => $resource->id,
            'url' => $resource->url,
        ];
    }

    private function replacePendingFigure(string $html, string $marker, string $replacement): string
    {
        $pattern = '/<figure\b[^>]*\bdata-pending-marker="'.preg_quote($marker, '/').'"[^>]*>.*?<\/figure>/s';

        $replaced = preg_replace_callback(
            $pattern,
            function (array $matches) use ($replacement): string {
                $original = $matches[0];

                if (! str_contains($original, 'doc-image--page-decoration')) {
                    return $this->preserveFigureLayoutAttributes($original, $replacement);
                }

                if (! preg_match('/class="([^"]+)"/', $original, $classMatch)) {
                    return $this->preserveFigureLayoutAttributes($original, $replacement);
                }

                $merged = preg_replace(
                    '/class="[^"]*"/',
                    'class="'.$classMatch[1].'"',
                    $replacement,
                    1,
                );

                if (! is_string($merged)) {
                    return $replacement;
                }

                // Page decorations: drop inline height/position; export CSS controls clipping.
                return (string) preg_replace_callback(
                    '/(<img\b[^>]*\bstyle=")([^"]*)(")/s',
                    static function (array $styleMatches): string {
                        $style = preg_replace('/\s*height:\s*[\d.]+px\s*;?/i', '', $styleMatches[2]) ?? $styleMatches[2];
                        $style = trim((string) $style, '; ');

                        return $style === ''
                            ? (preg_replace('/\sstyle="[^"]*"/', '', $styleMatches[0], 1) ?? $styleMatches[0])
                            : $styleMatches[1].$style.$styleMatches[3];
                    },
                    $merged,
                    1,
                );
            },
            $html,
            1,
        );

        return $replaced ?? $html;
    }

    private function preserveFigureLayoutAttributes(string $original, string $replacement): string
    {
        $updated = $replacement;

        if (preg_match('/\bstyle="([^"]*)"/', $original, $styleMatch)) {
            $layoutStyle = trim($styleMatch[1]);

            if ($layoutStyle !== '') {
                if (preg_match('/^<figure\b[^>]*\bstyle="([^"]*)"/', $updated, $figureStyleMatch)) {
                    $mergedStyle = trim($layoutStyle.'; '.trim($figureStyleMatch[1]), '; ');

                    $merged = preg_replace(
                        '/^<figure\b[^>]*\bstyle="[^"]*"/',
                        '<figure style="'.e($mergedStyle).'"',
                        $updated,
                        1,
                    );

                    $updated = is_string($merged) ? $merged : $updated;
                } else {
                    $withStyle = preg_replace('/^<figure\b/', '<figure style="'.e($layoutStyle).'"', $updated, 1);
                    $updated = is_string($withStyle) ? $withStyle : $updated;
                }
            }
        }

        return $this->preserveFigureDataAttributes($original, $updated);
    }

    private function preserveFigureDataAttributes(string $original, string $replacement): string
    {
        if (! preg_match('/^<figure\b([^>]*)>/', $original, $originalMatch)) {
            return $replacement;
        }

        $dataAttributes = [];
        if (preg_match_all('/\b(data-ooxml-(?:left|top|width|height))="(\d+)"/', $originalMatch[1], $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $dataAttributes[$match[1]] = $match[2];
            }
        }

        if ($dataAttributes === []) {
            return $replacement;
        }

        return (string) preg_replace_callback(
            '/^<figure\b([^>]*)>/',
            static function (array $match) use ($dataAttributes): string {
                $attrs = $match[1];
                foreach ($dataAttributes as $name => $value) {
                    if (preg_match('/\b'.preg_quote($name, '/').'="/', $attrs)) {
                        continue;
                    }

                    $attrs .= ' '.$name.'="'.$value.'"';
                }

                return '<figure'.$attrs.'>';
            },
            $replacement,
            1,
        );
    }
}

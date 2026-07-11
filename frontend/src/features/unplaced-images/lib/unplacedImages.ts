import type { DocumentBlock } from '@/entities/block';
import type { DocumentResource } from '@/entities/resource';

function collectImageSrcs(html: string | null | undefined): string[] {
  if (!html || typeof document === 'undefined') {
    return [];
  }

  const container = document.createElement('div');
  container.innerHTML = html;

  return Array.from(container.querySelectorAll('img[src]'))
    .map((img) => img.getAttribute('src') ?? '')
    .filter(Boolean);
}

export function collectPlacedResourceIds(
  blocks: DocumentBlock[],
  resources: DocumentResource[] = [],
): Set<string> {
  const ids = new Set<string>();
  const resourcesByStorageKey = new Map(
    resources
      .filter((resource) => resource.storage_key)
      .map((resource) => [resource.storage_key, resource.id]),
  );

  for (const block of blocks) {
    const assets = block.assets_json;

    if (assets && typeof assets.resource_id === 'string') {
      ids.add(assets.resource_id);
    }

    const tableImages = assets?.table_images;
    if (Array.isArray(tableImages)) {
      for (const item of tableImages) {
        if (item && typeof item === 'object' && typeof (item as { resource_id?: string }).resource_id === 'string') {
          ids.add((item as { resource_id: string }).resource_id);
        }
      }
    }

    if (block.type !== 'image') {
      continue;
    }

    for (const src of collectImageSrcs(block.html)) {
      for (const resource of resources) {
        if (!resource.url) {
          continue;
        }

        if (src === resource.url || src.includes(resource.url) || resource.url.includes(src)) {
          ids.add(resource.id);
        }
      }

      const keyMatch = src.match(/[?&]key=([^&"'\\s<>]+)/i);
      if (keyMatch) {
        const resourceId = resourcesByStorageKey.get(decodeURIComponent(keyMatch[1]));
        if (resourceId) {
          ids.add(resourceId);
        }
      }
    }
  }

  return ids;
}

export function listUnplacedImageResources(
  resources: DocumentResource[],
  blocks: DocumentBlock[],
): DocumentResource[] {
  const placed = collectPlacedResourceIds(blocks, resources);

  return resources.filter(
    (resource) =>
      (resource.type === 'image' || resource.type === 'user_upload') &&
      Boolean(resource.url) &&
      !placed.has(resource.id),
  );
}

export function buildImageBlockFromResource(resource: DocumentResource): {
  html: string;
  assets: Record<string, unknown>;
} {
  const url = resource.url ?? '';
  const meta = resource.meta_json ?? {};
  const width = typeof meta.width === 'number' ? meta.width : null;
  const height = typeof meta.height === 'number' ? meta.height : null;

  const figureAttrs = [
    width ? `data-ooxml-width="${width}"` : '',
    height ? `data-ooxml-height="${height}"` : '',
  ]
    .filter(Boolean)
    .join(' ');

  const figureStyle =
    width || height
      ? ` style="${[
          width ? `max-width:${width}px` : '',
          width ? `width:${width}px` : '',
          height ? `height:${height}px` : 'height:auto',
        ]
          .filter(Boolean)
          .join('; ')}"`
      : '';

  const imgStyle =
    width || height
      ? ` style="${[
          width ? `max-width:${width}px` : '',
          width ? `width:${width}px` : '',
          'height:auto',
        ]
          .filter(Boolean)
          .join('; ')}"`
      : '';

  return {
    html: `<figure class="doc-image doc-image--flowing"${figureAttrs ? ` ${figureAttrs}` : ''}${figureStyle}><img src="${url.replace(/"/g, '&quot;')}" alt=""${imgStyle} /></figure>`,
    assets: {
      resource_id: resource.id,
      url,
    },
  };
}

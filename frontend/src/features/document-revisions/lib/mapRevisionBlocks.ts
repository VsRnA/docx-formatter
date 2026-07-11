import type { DocumentBlock } from '@/entities/block';

export function mapRevisionBlocks(snapshot: Array<Record<string, unknown>>): DocumentBlock[] {
  return snapshot.map((item) => ({
    id: String(item.id),
    document_id: String(item.document_id ?? ''),
    type: String(item.type) as DocumentBlock['type'],
    sort: Number(item.sort ?? 0),
    html: typeof item.html === 'string' ? item.html : null,
    content_json: (item.content_json as DocumentBlock['content_json']) ?? null,
    text_original: typeof item.text_original === 'string' ? item.text_original : null,
    text_translated: typeof item.text_translated === 'string' ? item.text_translated : null,
    translation_status: (item.translation_status as DocumentBlock['translation_status']) ?? 'skipped',
    styles_json: (item.styles as DocumentBlock['styles_json']) ?? null,
    meta_json: (item.meta as DocumentBlock['meta_json']) ?? null,
    assets_json: (item.assets as DocumentBlock['assets_json']) ?? null,
  }));
}

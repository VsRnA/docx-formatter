import { describe, expect, it } from 'vitest';
import { sortBlocks } from './sortBlocks';
import type { DocumentBlock } from '../model/types';

function block(id: string, sort: number): DocumentBlock {
  return {
    id,
    document_id: 'doc-1',
    type: 'paragraph',
    sort,
    html: null,
    content_json: null,
    text_original: null,
    text_translated: null,
    translation_status: 'skipped',
    styles_json: null,
    meta_json: null,
    assets_json: null,
  };
}

describe('sortBlocks', () => {
  it('sorts blocks by sort field ascending', () => {
    const sorted = sortBlocks([block('c', 2), block('a', 0), block('b', 1)]);

    expect(sorted.map((item) => item.id)).toEqual(['a', 'b', 'c']);
  });
});

import { describe, expect, it } from 'vitest';
import { getSchema } from '@tiptap/core';
import { AttrStep, Transform } from '@tiptap/pm/transform';
import { blocksToEditorJson } from './blockConversion';
import { getDocumentEditorExtensions } from './getExtensions';
import type { DocumentBlock } from '@/entities/block';

const schema = getSchema(getDocumentEditorExtensions());

function makeBlock(type: DocumentBlock['type'], html: string, id = 'block-1'): DocumentBlock {
  return {
    id,
    document_id: 'doc-1',
    type,
    sort: 0,
    html,
    content_json: null,
    text_original: null,
    text_translated: null,
    translation_status: 'skipped',
    styles_json: null,
    meta_json: null,
    assets_json: null,
  };
}

function assertValidDocJson(blocks: DocumentBlock[]) {
  const json = blocksToEditorJson(blocks);
  expect(() => schema.nodeFromJSON(json)).not.toThrow();

  const doc = schema.nodeFromJSON(json);
  doc.forEach((node) => {
    if (node.type.name === 'textDocBlock') {
      expect(node.type.validContent(node.content), node.content.toString()).toBe(true);
      expect(node.content.childCount).toBeGreaterThan(0);
    }
  });
}

describe('blocksToEditorJson', () => {
  const samples: Array<[DocumentBlock['type'], string]> = [
    ['paragraph', ''],
    ['paragraph', '<p></p>'],
    ['paragraph', '<!-- comment -->'],
    ['paragraph', '<br>'],
    ['paragraph', '<div class="doc-figure-gallery"><p>x</p></div>'],
    ['html_raw', '<div class="doc-symbol-row"><span class="doc-textbox">x</span></div>'],
    ['formula', '<span data-doc-formula="1">E=mc2</span>'],
    ['caption', '<p class="doc-figure-caption">Caption</p>'],
    ['paragraph', '<table><tr><td>a</td></tr></table>'],
    ['list', '<ul><li>one</li><li>two</li></ul>'],
    ['heading', '<h2>Title</h2>'],
  ];

  it.each(samples)('produces valid textDocBlock content for type=%s', (type, html) => {
    assertValidDocJson([makeBlock(type, html)]);
  });

  it('repairs orphan textDocBlock nodes with invalid inner content', () => {
    const json = {
      type: 'doc',
      content: [
        {
          type: 'textDocBlock',
          attrs: { blockId: null, blockType: 'paragraph', pageBreakBefore: false },
          content: [],
        },
      ],
    };

    const doc = schema.nodeFromJSON(json);
    const node = doc.firstChild!;
    expect(node.type.validContent(node.content)).toBe(false);

    const fixed = node.type.createAndFill({ ...node.attrs, blockId: 'new-id' });
    expect(fixed).not.toBeNull();
    expect(fixed!.type.validContent(fixed!.content)).toBe(true);
    expect(fixed!.attrs.blockId).toBe('new-id');
  });

  it('uses AttrStep for doc blocks with valid content', () => {
    const json = blocksToEditorJson([makeBlock('paragraph', '<p>Hello</p>')]);
    const doc = schema.nodeFromJSON(json);
    const tr = new Transform(doc);
    tr.step(new AttrStep(0, 'blockId', 'replacement-id'));
    expect(tr.doc.firstChild?.attrs.blockId).toBe('replacement-id');
  });

  it('uses AttrStep for atom image blocks', () => {
    const json = {
      type: 'doc',
      content: [
        {
          type: 'imageDocBlock',
          attrs: {
            blockId: null,
            blockType: 'image',
            html: '<figure></figure>',
            pageBreakBefore: false,
            displayWidth: null,
          },
        },
      ],
    };

    const doc = schema.nodeFromJSON(json);
    const tr = new Transform(doc);
    tr.step(new AttrStep(0, 'blockId', 'new-id'));
    expect(tr.doc.firstChild?.attrs.blockId).toBe('new-id');
  });
});

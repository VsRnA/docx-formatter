import { generateHTML, generateJSON } from '@tiptap/html';
import type { JSONContent } from '@tiptap/core';
import type { Editor } from '@tiptap/react';
import type { DocumentBlock } from '@/entities/block';
import type { SaveDraftBlockPayload } from '@/entities/document';
import { defaultTableHtml } from '../defaultBlockHtml';
import { repairBlockWrapperHtml } from '../blockEditorHtml';
import { hasPageBreakBefore, mergeBlockMetaWithPageBreak } from '../blockPageBreak';
import { getInnerContentExtensions, getTableContentExtensions } from './getExtensions';
import { createUuid } from '@/shared/lib/uuid';

const ATOM_BLOCK_TYPES = new Set(['image']);

function createBlockId(): string {
  return createUuid();
}

function defaultTableNodeJson(): JSONContent {
  try {
    const json = generateJSON(defaultTableHtml(), getTableContentExtensions());
    const table = json.content?.find((node: JSONContent) => node.type === 'table');
    if (table) {
      return table;
    }
  } catch {
    // fall through
  }

  return {
    type: 'table',
    content: [
      {
        type: 'tableRow',
        content: [
          { type: 'tableCell', content: [{ type: 'paragraph' }] },
          { type: 'tableCell', content: [{ type: 'paragraph' }] },
        ],
      },
      {
        type: 'tableRow',
        content: [
          { type: 'tableCell', content: [{ type: 'paragraph' }] },
          { type: 'tableCell', content: [{ type: 'paragraph' }] },
        ],
      },
    ],
  };
}

export function parseTableNodeFromHtml(html: string): JSONContent {
  const repaired = repairBlockWrapperHtml(html).trim();
  if (!repaired) {
    return defaultTableNodeJson();
  }

  try {
    const json = generateJSON(repaired, getTableContentExtensions());
    const table = json.content?.find((node: JSONContent) => node.type === 'table');
    if (table) {
      return table;
    }
  } catch {
    // fall through
  }

  return defaultTableNodeJson();
}

function parseInnerHtml(html: string): JSONContent[] {
  const trimmed = html.trim();
  if (trimmed === '') {
    return [{ type: 'paragraph' }];
  }

  try {
    const json = generateJSON(trimmed, getInnerContentExtensions());
    return json.content?.length ? json.content : [{ type: 'paragraph' }];
  } catch {
    return [{ type: 'paragraph', content: [{ type: 'text', text: trimmed }] }];
  }
}

export function blocksToEditorJson(blocks: DocumentBlock[]): JSONContent {
  return {
    type: 'doc',
    content: blocks.map((block) => {
      const pageBreakBefore = hasPageBreakBefore(block);

      if (block.type === 'image') {
        return {
          type: 'imageDocBlock',
          attrs: {
            blockId: block.id,
            blockType: 'image',
            html: repairBlockWrapperHtml(block.html ?? ''),
            pageBreakBefore,
          },
        };
      }

      if (block.type === 'table') {
        return {
          type: 'tableDocBlock',
          attrs: {
            blockId: block.id,
            blockType: 'table',
            pageBreakBefore,
          },
          content: [parseTableNodeFromHtml(block.html ?? '')],
        };
      }

      return {
        type: 'textDocBlock',
        attrs: {
          blockId: block.id,
          blockType: block.type,
          pageBreakBefore,
        },
        content: parseInnerHtml(repairBlockWrapperHtml(block.html ?? '')),
      };
    }),
  };
}

function serializeInnerContent(content: JSONContent[] | undefined): string {
  if (!content || content.length === 0) {
    return '<p></p>';
  }

  return generateHTML({ type: 'doc', content }, getInnerContentExtensions());
}

function serializeTableContent(content: JSONContent[] | undefined): string {
  if (!content || content.length === 0) {
    return defaultTableHtml();
  }

  return generateHTML({ type: 'doc', content }, getTableContentExtensions());
}

function findBlockMeta(blocks: DocumentBlock[], blockId: string | null): DocumentBlock | undefined {
  if (!blockId) {
    return undefined;
  }

  return blocks.find((block) => block.id === blockId);
}

export function extractBlockUpdatesFromEditor(
  editor: Editor,
  blocks: DocumentBlock[],
): SaveDraftBlockPayload[] {
  const doc = editor.state.doc;
  const updates: SaveDraftBlockPayload[] = [];

  doc.forEach((node, _offset, index) => {
    const blockId = node.attrs.blockId as string | null;
    const source = findBlockMeta(blocks, blockId);
    const blockType =
      (node.attrs.blockType as string | undefined)
      ?? source?.type
      ?? 'paragraph';

    let html: string;
    if (node.type.name === 'imageDocBlock') {
      html = repairBlockWrapperHtml((node.attrs.html as string) ?? '');
    } else if (node.type.name === 'tableDocBlock') {
      html = serializeTableContent(node.content.toJSON() as JSONContent[]);
    } else {
      html = serializeInnerContent(node.content.toJSON() as JSONContent[]);
    }

    const wrapper = document.createElement('div');
    wrapper.dataset.blockId = blockId ?? '';
    wrapper.dataset.blockType = blockType;
    if (node.attrs.pageBreakBefore) {
      wrapper.dataset.pageBreakBefore = 'true';
    }

    updates.push({
      id: blockId ?? source?.id ?? createBlockId(),
      type: (ATOM_BLOCK_TYPES.has(blockType) ? blockType : source?.type ?? blockType) as DocumentBlock['type'],
      sort: index,
      html,
      styles: source?.styles_json,
      meta: mergeBlockMetaWithPageBreak(
        source ?? ({ meta_json: null } as DocumentBlock),
        wrapper,
      ),
      assets: source?.assets_json,
    });
  });

  return updates;
}

export function findBlockDomPos(editor: Editor, blockId: string): number | null {
  let found: number | null = null;

  editor.state.doc.forEach((node, offset) => {
    if (node.attrs.blockId === blockId && found === null) {
      found = offset;
    }
  });

  return found;
}

export function getActiveBlockFromEditor(editor: Editor): { id: string; type: string } | null {
  const { $from } = editor.state.selection;

  for (let depth = $from.depth; depth >= 0; depth -= 1) {
    const node = $from.node(depth);
    if (node.attrs.blockId) {
      return {
        id: node.attrs.blockId as string,
        type: (node.attrs.blockType as string) ?? 'paragraph',
      };
    }
  }

  return null;
}

export function createTextBlockContent(blockType: string, html: string): JSONContent {
  return {
    type: 'textDocBlock',
    attrs: {
      blockId: null,
      blockType,
      pageBreakBefore: false,
    },
    content: parseInnerHtml(html),
  };
}

export function createAtomBlockContent(
  blockType: 'image' | 'table',
  html: string,
  assets?: Record<string, unknown> | null,
): JSONContent {
  void assets;

  if (blockType === 'image') {
    return {
      type: 'imageDocBlock',
      attrs: {
        blockId: null,
        blockType: 'image',
        html: repairBlockWrapperHtml(html),
        pageBreakBefore: false,
      },
    };
  }

  return {
    type: 'tableDocBlock',
    attrs: {
      blockId: null,
      blockType: 'table',
      pageBreakBefore: false,
    },
    content: [parseTableNodeFromHtml(html)],
  };
}

export function createTableDocBlockReplacement(
  blockId: string,
  blockType: string,
  pageBreakBefore: boolean,
  html: string,
): JSONContent {
  return {
    type: 'tableDocBlock',
    attrs: {
      blockId,
      blockType,
      pageBreakBefore,
    },
    content: [parseTableNodeFromHtml(html)],
  };
}

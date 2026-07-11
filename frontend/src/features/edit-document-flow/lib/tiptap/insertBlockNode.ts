import type { Editor } from '@tiptap/react';
import type { DocumentBlock } from '@/entities/block';
import { hasPageBreakBefore } from '../blockPageBreak';
import { blocksToEditorJson } from './blockConversion';

export function blockToNodeJson(block: DocumentBlock) {
  return blocksToEditorJson([block]).content?.[0] ?? {
    type: 'textDocBlock',
    attrs: {
      blockId: block.id,
      blockType: block.type,
      pageBreakBefore: hasPageBreakBefore(block),
    },
    content: [{ type: 'paragraph' }],
  };
}

export function findBlockNodePos(editor: Editor, blockId: string): number | null {
  let found: number | null = null;

  editor.state.doc.descendants((node, pos) => {
    if (found !== null) {
      return false;
    }

    if (node.attrs.blockId === blockId) {
      found = pos;
      return false;
    }

    return true;
  });

  return found;
}

function isBlockVisibleInDom(editor: Editor, block: DocumentBlock): boolean {
  const pos = findBlockNodePos(editor, block.id);
  if (pos === null) {
    return false;
  }

  const dom = editor.view.nodeDOM(pos);
  if (!(dom instanceof HTMLElement)) {
    return false;
  }

  if (block.type === 'image') {
    return Boolean(
      dom.querySelector('figure.doc-image, .doc-flow-image-block, img, .doc-image__placeholder'),
    );
  }

  if (block.type === 'table') {
    return Boolean(dom.querySelector('table'));
  }

  return dom.textContent !== null;
}

export function insertBlockNodeAt(
  editor: Editor,
  insertPos: number,
  block: DocumentBlock,
): boolean {
  try {
    const nodeJson = blockToNodeJson(block);
    const node = editor.schema.nodeFromJSON(nodeJson);
    editor.view.dispatch(editor.state.tr.insert(insertPos, node));
    return isBlockVisibleInDom(editor, block);
  } catch {
    return false;
  }
}

export function resyncEditorBlocks(editor: Editor, blocks: DocumentBlock[]): void {
  editor.commands.setContent(blocksToEditorJson(blocks), { emitUpdate: false });

  // React NodeViews (image blocks) mount asynchronously after setContent.
  requestAnimationFrame(() => {
    if (editor.isDestroyed) {
      return;
    }

    editor.view.dispatch(editor.state.tr);
  });
}

export function insertBlockAfterNode(
  editor: Editor,
  afterBlockId: string | null,
  block: DocumentBlock,
  allBlocks: DocumentBlock[],
): void {
  const shouldForceResync = block.type === 'image';

  if (shouldForceResync) {
    resyncEditorBlocks(editor, allBlocks);
    return;
  }

  let insertPos = editor.state.doc.content.size;

  if (afterBlockId) {
    const pos = findBlockNodePos(editor, afterBlockId);
    if (pos !== null) {
      const afterNode = editor.state.doc.nodeAt(pos);
      if (afterNode) {
        insertPos = pos + afterNode.nodeSize;
      }
    }
  }

  const inserted = insertBlockNodeAt(editor, insertPos, block);
  if (!inserted) {
    resyncEditorBlocks(editor, allBlocks);
  }
}

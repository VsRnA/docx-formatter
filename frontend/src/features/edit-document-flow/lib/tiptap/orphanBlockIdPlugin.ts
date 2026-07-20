import { Extension } from '@tiptap/core';
import { Plugin, PluginKey } from '@tiptap/pm/state';
import { createUuid } from '@/shared/lib/uuid';

const DOC_BLOCK_TYPES = new Set(['textDocBlock', 'imageDocBlock', 'tableDocBlock']);

function createBlockId(): string {
  return createUuid();
}

export const OrphanBlockIdPlugin = Extension.create({
  name: 'orphanBlockId',

  addProseMirrorPlugins() {
    return [
      new Plugin({
        key: new PluginKey('orphanBlockId'),
        appendTransaction: (_transactions, _oldState, newState) => {
          const { doc, tr } = newState;
          let modified = false;

          doc.forEach((node, offset) => {
            if (!DOC_BLOCK_TYPES.has(node.type.name)) {
              return;
            }

            const blockId = node.attrs.blockId as string | null;
            if (blockId) {
              return;
            }

            tr.setNodeMarkup(offset, undefined, {
              ...node.attrs,
              blockId: createBlockId(),
            });
            modified = true;
          });

          return modified ? tr : null;
        },
      }),
    ];
  },
});

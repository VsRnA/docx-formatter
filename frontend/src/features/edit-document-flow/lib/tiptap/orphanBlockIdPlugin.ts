import { Extension } from '@tiptap/core';
import { Plugin, PluginKey } from '@tiptap/pm/state';
import { AttrStep } from '@tiptap/pm/transform';
import { createUuid } from '@/shared/lib/uuid';

const DOC_BLOCK_TYPES = new Set(['textDocBlock', 'imageDocBlock', 'tableDocBlock']);

function createBlockId(): string {
  return createUuid();
}

function hasBlockId(value: unknown): value is string {
  return typeof value === 'string' && value.length > 0;
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
            if (hasBlockId(blockId)) {
              return;
            }

            const nextBlockId = createBlockId();

            if (!node.type.validContent(node.content)) {
              const fixed = node.type.createAndFill({
                ...node.attrs,
                blockId: nextBlockId,
              });

              if (fixed) {
                tr.replaceWith(offset, offset + node.nodeSize, fixed);
                modified = true;
              }

              return;
            }

            tr.step(new AttrStep(offset, 'blockId', nextBlockId));
            modified = true;
          });

          return modified ? tr : null;
        },
      }),
    ];
  },
});

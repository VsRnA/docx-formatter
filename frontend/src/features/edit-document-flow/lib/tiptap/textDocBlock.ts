import { Node, mergeAttributes } from '@tiptap/core';
import { PAGE_BREAK_BEFORE_CLASS } from '../blockPageBreak';

export const TextDocBlock = Node.create({
  name: 'textDocBlock',
  group: 'docBlock',
  content: 'block+',
  defining: true,
  isolating: true,

  addAttributes() {
    return {
      blockId: { default: null },
      blockType: { default: 'paragraph' },
      pageBreakBefore: { default: false },
    };
  },

  parseHTML() {
    return [
      {
        tag: 'div[data-block-id]',
        getAttrs: (element) => {
          if (!(element instanceof HTMLElement)) {
            return false;
          }

          const blockType = element.dataset.blockType ?? 'paragraph';
          if (blockType === 'image' || blockType === 'table') {
            return false;
          }

          return {
            blockId: element.dataset.blockId ?? null,
            blockType,
            pageBreakBefore:
              element.dataset.pageBreakBefore === 'true'
              || element.classList.contains(PAGE_BREAK_BEFORE_CLASS),
          };
        },
      },
    ];
  },

  renderHTML({ node, HTMLAttributes }) {
    const classes = ['doc-block', 'doc-flow-block'];
    if (node.attrs.pageBreakBefore) {
      classes.push('doc-block--page-break-before', PAGE_BREAK_BEFORE_CLASS);
    }

    return [
      'div',
      mergeAttributes(HTMLAttributes, {
        'data-block-id': node.attrs.blockId,
        'data-block-type': node.attrs.blockType,
        'data-page-break-before': node.attrs.pageBreakBefore ? 'true' : undefined,
        class: classes.join(' '),
      }),
      0,
    ];
  },
});

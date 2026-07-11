import { Node, mergeAttributes } from '@tiptap/core';
import { PAGE_BREAK_BEFORE_CLASS } from '../blockPageBreak';

export const TableDocBlock = Node.create({
  name: 'tableDocBlock',
  group: 'docBlock',
  content: 'table',
  defining: true,
  isolating: true,

  addAttributes() {
    return {
      blockId: { default: null },
      blockType: { default: 'table' },
      pageBreakBefore: { default: false },
    };
  },

  parseHTML() {
    return [
      {
        tag: 'div[data-block-type="table"]',
        getAttrs: (element) => {
          if (!(element instanceof HTMLElement)) {
            return false;
          }

          return {
            blockId: element.dataset.blockId ?? null,
            blockType: 'table',
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
        'data-block-type': 'table',
        'data-page-break-before': node.attrs.pageBreakBefore ? 'true' : undefined,
        class: classes.join(' '),
      }),
      0,
    ];
  },
});

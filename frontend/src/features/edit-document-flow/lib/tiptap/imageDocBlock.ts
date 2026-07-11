import { Node, mergeAttributes } from '@tiptap/core';
import { ReactNodeViewRenderer } from '@tiptap/react';
import { PAGE_BREAK_BEFORE_CLASS } from '../blockPageBreak';
import { ImageBlockNodeView } from './ImageBlockNodeView';

export interface ImageDocBlockOptions {
  onEditImage?: ((blockId: string) => void) | null;
}

export const ImageDocBlock = Node.create<ImageDocBlockOptions>({
  name: 'imageDocBlock',
  group: 'docBlock',
  atom: true,
  selectable: true,
  draggable: false,

  addOptions() {
    return {
      onEditImage: null,
    };
  },

  addAttributes() {
    return {
      blockId: { default: null },
      blockType: { default: 'image' },
      html: { default: '' },
      pageBreakBefore: { default: false },
      displayWidth: { default: null as number | null },
    };
  },

  parseHTML() {
    return [
      {
        tag: 'div[data-block-type="image"]',
        getAttrs: (element) => {
          if (!(element instanceof HTMLElement)) {
            return false;
          }

          const figure = element.querySelector<HTMLElement>('figure.doc-image');
          const widthAttr = figure?.getAttribute('data-ooxml-width')
            ?? figure?.style.width?.replace('px', '');

          return {
            blockId: element.dataset.blockId ?? null,
            blockType: 'image',
            html: element.innerHTML,
            pageBreakBefore:
              element.dataset.pageBreakBefore === 'true'
              || element.classList.contains(PAGE_BREAK_BEFORE_CLASS),
            displayWidth: widthAttr ? Number(widthAttr) : null,
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
        'data-block-type': 'image',
        'data-page-break-before': node.attrs.pageBreakBefore ? 'true' : undefined,
        class: classes.join(' '),
      }),
      node.attrs.html,
    ];
  },

  addNodeView() {
    return ReactNodeViewRenderer(ImageBlockNodeView);
  },
});

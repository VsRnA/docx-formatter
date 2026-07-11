import Document from '@tiptap/extension-document';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Strike from '@tiptap/extension-strike';
import TextAlign from '@tiptap/extension-text-align';
import { TextStyle } from '@tiptap/extension-text-style';
import { Color } from '@tiptap/extension-color';
import Highlight from '@tiptap/extension-highlight';
import Link from '@tiptap/extension-link';
import FontFamily from '@tiptap/extension-font-family';
import { FontSizeExtension } from './fontSizeExtension';
import { DocumentBlockDocument } from './documentBlockDocument';
import { TextDocBlock } from './textDocBlock';
import { ImageDocBlock, type ImageDocBlockOptions } from './imageDocBlock';
import { TableDocBlock } from './tableDocBlock';
import { TabKeyboardExtension } from './tabKeyboard';
import { getTableExtensions } from './tableExtensions';

export { getTableExtensions } from './tableExtensions';

export function getDocumentEditorExtensions(options?: ImageDocBlockOptions) {
  return [
    DocumentBlockDocument,
    TextDocBlock,
    ImageDocBlock.configure({
      onEditImage: options?.onEditImage ?? null,
    }),
    TableDocBlock,
    StarterKit.configure({
      document: false,
      heading: { levels: [2, 3] },
      strike: false,
    }),
    ...getTableExtensions(),
    Underline,
    Strike,
    TextStyle,
    Color,
    Highlight.configure({ multicolor: true }),
    Link.configure({
      openOnClick: false,
      HTMLAttributes: { class: 'doc-link' },
    }),
    FontFamily,
    FontSizeExtension,
    TextAlign.configure({
      types: ['heading', 'paragraph'],
    }),
    TabKeyboardExtension,
  ];
}

/** Extensions for parsing inner HTML of a text block (without docBlock wrappers). */
export function getInnerContentExtensions() {
  return [
    StarterKit.configure({
      heading: { levels: [2, 3] },
      strike: false,
    }),
    Underline,
    Strike,
    TextStyle,
    Color,
    Highlight.configure({ multicolor: true }),
    Link.configure({
      openOnClick: false,
      HTMLAttributes: { class: 'doc-link' },
    }),
    FontFamily,
    FontSizeExtension,
    TextAlign.configure({
      types: ['heading', 'paragraph'],
    }),
  ];
}

/** Extensions for parsing/serializing table HTML inside a tableDocBlock. */
export function getTableContentExtensions() {
  return [
    Document,
    ...getTableExtensions(),
    StarterKit.configure({
      document: false,
      heading: false,
      blockquote: false,
      codeBlock: false,
      horizontalRule: false,
    }),
    Underline,
    TextStyle,
    FontFamily,
    FontSizeExtension,
    TextAlign.configure({
      types: ['heading', 'paragraph'],
    }),
  ];
}

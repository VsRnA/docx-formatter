import Document from '@tiptap/extension-document';

export const DocumentBlockDocument = Document.extend({
  content: '(textDocBlock | imageDocBlock | tableDocBlock)+',
});

import { useEffect, useState } from 'react';
import type { Editor } from '@tiptap/react';
import { DEFAULT_TOOLBAR_STATE, FONT_FAMILIES, FONT_SIZES, type FormatToolbarState } from '../lib/formatConstants';

function readBlockStyle(editor: Editor): string {
  if (editor.isActive('heading', { level: 2 })) {
    return 'heading2';
  }

  if (editor.isActive('heading', { level: 3 })) {
    return 'heading3';
  }

  return 'paragraph';
}

function readFontFamily(editor: Editor): string {
  const attributes = editor.getAttributes('textStyle');
  const value = typeof attributes.fontFamily === 'string' ? attributes.fontFamily : null;
  if (value && FONT_FAMILIES.some((font) => font.value === value)) {
    return value;
  }

  return DEFAULT_TOOLBAR_STATE.fontFamily;
}

function readFontSize(editor: Editor): string {
  const attributes = editor.getAttributes('textStyle');
  const value = typeof attributes.fontSize === 'string' ? attributes.fontSize : null;
  if (value && FONT_SIZES.some((size) => size.value === value)) {
    return value;
  }

  return DEFAULT_TOOLBAR_STATE.fontSize;
}

function readToolbarStateFromEditor(editor: Editor | null): FormatToolbarState {
  if (!editor) {
    return DEFAULT_TOOLBAR_STATE;
  }

  const textStyle = editor.getAttributes('textStyle');
  const highlight = editor.getAttributes('highlight');

  return {
    bold: editor.isActive('bold'),
    italic: editor.isActive('italic'),
    underline: editor.isActive('underline'),
    strike: editor.isActive('strike'),
    alignLeft: editor.isActive({ textAlign: 'left' }),
    alignCenter: editor.isActive({ textAlign: 'center' }),
    alignRight: editor.isActive({ textAlign: 'right' }),
    alignJustify: editor.isActive({ textAlign: 'justify' }),
    orderedList: editor.isActive('orderedList'),
    unorderedList: editor.isActive('bulletList'),
    blockStyle: readBlockStyle(editor),
    fontFamily: readFontFamily(editor),
    fontSize: readFontSize(editor),
    textColor: typeof textStyle.color === 'string' ? textStyle.color : null,
    highlightColor: typeof highlight.color === 'string' ? highlight.color : null,
    linkHref: editor.getAttributes('link').href ?? null,
  };
}

export function useFormatToolbarState(editor: Editor | null) {
  const [state, setState] = useState(DEFAULT_TOOLBAR_STATE);

  useEffect(() => {
    if (!editor) {
      setState(DEFAULT_TOOLBAR_STATE);
      return;
    }

    const update = () => {
      setState(readToolbarStateFromEditor(editor));
    };

    update();
    editor.on('selectionUpdate', update);
    editor.on('transaction', update);

    return () => {
      editor.off('selectionUpdate', update);
      editor.off('transaction', update);
    };
  }, [editor]);

  return state;
}

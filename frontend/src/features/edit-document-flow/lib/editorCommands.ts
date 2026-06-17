export const FONT_FAMILIES = [
  { label: 'Times New Roman', value: 'Times New Roman' },
  { label: 'Arial', value: 'Arial' },
  { label: 'Calibri', value: 'Calibri' },
  { label: 'Georgia', value: 'Georgia' },
  { label: 'Verdana', value: 'Verdana' },
] as const;

export const FONT_SIZES = [
  { label: '10', value: '10pt' },
  { label: '11', value: '11pt' },
  { label: '12', value: '12pt' },
  { label: '14', value: '14pt' },
  { label: '16', value: '16pt' },
  { label: '18', value: '18pt' },
  { label: '24', value: '24pt' },
] as const;

export const BLOCK_STYLES = [
  { label: 'Обычный текст', value: 'p' },
  { label: 'Заголовок 1', value: 'h1' },
  { label: 'Заголовок 2', value: 'h2' },
  { label: 'Заголовок 3', value: 'h3' },
  { label: 'Заголовок 4', value: 'h4' },
] as const;

export interface FormatToolbarState {
  bold: boolean;
  italic: boolean;
  underline: boolean;
  alignLeft: boolean;
  alignCenter: boolean;
  alignRight: boolean;
  orderedList: boolean;
  unorderedList: boolean;
}

export const DEFAULT_TOOLBAR_STATE: FormatToolbarState = {
  bold: false,
  italic: false,
  underline: false,
  alignLeft: true,
  alignCenter: false,
  alignRight: false,
  orderedList: false,
  unorderedList: false,
};

export function isSelectionInsideEditor(editor: HTMLElement | null): boolean {
  if (!editor) return false;
  const selection = window.getSelection();
  if (!selection || selection.rangeCount === 0) return false;
  const node = selection.anchorNode;
  return Boolean(node && editor.contains(node));
}

export function readToolbarState(editor: HTMLElement | null): FormatToolbarState {
  if (!isSelectionInsideEditor(editor)) {
    return DEFAULT_TOOLBAR_STATE;
  }

  return {
    bold: document.queryCommandState('bold'),
    italic: document.queryCommandState('italic'),
    underline: document.queryCommandState('underline'),
    alignLeft: document.queryCommandState('justifyLeft'),
    alignCenter: document.queryCommandState('justifyCenter'),
    alignRight: document.queryCommandState('justifyRight'),
    orderedList: document.queryCommandState('insertOrderedList'),
    unorderedList: document.queryCommandState('insertUnorderedList'),
  };
}

export function execEditorCommand(
  editor: HTMLElement | null,
  command: string,
  value?: string,
): void {
  if (!editor) return;
  editor.focus();
  document.execCommand(command, false, value);
}

export function applyBlockStyle(editor: HTMLElement | null, tag: string): void {
  execEditorCommand(editor, 'formatBlock', tag);
}

export function applyFontFamily(editor: HTMLElement | null, fontFamily: string): void {
  execEditorCommand(editor, 'fontName', fontFamily);
}

export function applyFontSize(editor: HTMLElement | null, fontSize: string): void {
  if (!editor) return;
  editor.focus();

  const selection = window.getSelection();
  if (!selection || selection.rangeCount === 0) return;

  if (selection.isCollapsed) {
    execEditorCommand(editor, 'fontSize', '4');
    editor.querySelectorAll('font[size]').forEach((element) => {
      if (!(element instanceof HTMLElement)) return;
      const span = document.createElement('span');
      span.style.fontSize = fontSize;
      span.innerHTML = element.innerHTML;
      element.replaceWith(span);
    });
    return;
  }

  const range = selection.getRangeAt(0);
  const span = document.createElement('span');
  span.style.fontSize = fontSize;
  try {
    range.surroundContents(span);
  } catch {
    execEditorCommand(editor, 'insertHTML', `<span style="font-size:${fontSize}">${range.toString()}</span>`);
  }
}

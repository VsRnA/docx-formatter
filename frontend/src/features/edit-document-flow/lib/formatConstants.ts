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
  { label: 'Обычный текст', value: 'paragraph' },
  { label: 'Заголовок 2', value: 'heading2' },
  { label: 'Заголовок 3', value: 'heading3' },
] as const;

export interface FormatToolbarState {
  bold: boolean;
  italic: boolean;
  underline: boolean;
  strike: boolean;
  alignLeft: boolean;
  alignCenter: boolean;
  alignRight: boolean;
  alignJustify: boolean;
  orderedList: boolean;
  unorderedList: boolean;
  blockStyle: string;
  fontFamily: string;
  fontSize: string;
  textColor: string | null;
  highlightColor: string | null;
  linkHref: string | null;
}

export const DEFAULT_TOOLBAR_STATE: FormatToolbarState = {
  bold: false,
  italic: false,
  underline: false,
  strike: false,
  alignLeft: true,
  alignCenter: false,
  alignRight: false,
  alignJustify: false,
  orderedList: false,
  unorderedList: false,
  blockStyle: 'paragraph',
  fontFamily: 'Times New Roman',
  fontSize: '12pt',
  textColor: null,
  highlightColor: null,
  linkHref: null,
};

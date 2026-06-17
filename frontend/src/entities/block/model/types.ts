export type BlockType =
  | 'heading'
  | 'paragraph'
  | 'list'
  | 'table'
  | 'image'
  | 'caption'
  | 'image_text'
  | 'link_block'
  | 'html_raw'
  | 'formula';

export type TranslationStatus = 'pending' | 'done' | 'failed' | 'skipped';

export interface BlockMetaJson {
  source?: string;
  parse?: boolean;
  content_edited?: boolean;
  needs_review?: boolean;
  ai_normalized?: boolean;
  confidence?: number;
  page_break_before?: boolean;
  paragraph_style?: string;
  list_marker?: string | null;
  ooxml_scope_index?: number;
  [key: string]: unknown;
}

export interface DocumentBlock {
  id: string;
  document_id: string;
  type: BlockType;
  sort: number;
  html: string | null;
  content_json: Record<string, unknown> | null;
  text_original: string | null;
  text_translated: string | null;
  translation_status: TranslationStatus;
  styles_json: Record<string, unknown> | null;
  meta_json: BlockMetaJson | null;
  assets_json: Record<string, unknown> | null;
}

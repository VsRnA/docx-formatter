import type { ProcessingStage } from '@/shared/constants/processingStageLabels';
import type { DocumentLayout } from '@/shared/lib/documentLayoutStyle';

export type DocumentStatus =
  | 'uploading'
  | 'processing'
  | 'ready'
  | 'failed'
  | 'draft'
  | 'published';

export type { ProcessingStage };

export interface ParseCoverage {
  coverage_ratio?: number;
  passes_threshold?: boolean;
  source_char_count?: number;
  blocks_char_count?: number;
}

export interface Document {
  id: string;
  title: string;
  slug: string | null;
  status: DocumentStatus;
  processing_stage: ProcessingStage | null;
  processing_error: string | null;
  language_from: string;
  language_to: string;
  created_at: string;
  updated_at: string;
  revisions_count: number;
  layout?: DocumentLayout | null;
}

export interface DocumentListResponse {
  data: Document[];
  meta?: {
    current_page: number;
    last_page: number;
    total: number;
  };
}

export interface ParseWarningSummary {
  type: string;
  count: number;
}

export interface DocumentStatusResponse {
  id: string;
  status: DocumentStatus;
  processing_stage: ProcessingStage | null;
  processing_error: string | null;
  parse_coverage?: ParseCoverage | null;
  parse_warnings_count?: number;
  parse_warnings?: ParseWarningSummary[];
  has_translated_docx?: boolean;
}

import { apiClient } from '@/shared/api';

export interface DocumentRevisionSummary {
  id: string;
  document_id: string;
  trigger: string;
  label: string | null;
  blocks_count: number;
  summary: string | null;
  created_at: string;
}

export interface DocumentRevisionDetail extends DocumentRevisionSummary {
  blocks_snapshot: Array<Record<string, unknown>>;
  html_draft_snapshot: string | null;
}

export const documentRevisionApi = {
  list(documentId: string) {
    return apiClient.get<{ data: DocumentRevisionSummary[] }>(`/documents/${documentId}/revisions`);
  },

  get(documentId: string, revisionId: string) {
    return apiClient.get<{ data: DocumentRevisionDetail }>(
      `/documents/${documentId}/revisions/${revisionId}`,
    );
  },

  create(documentId: string, label?: string) {
    return apiClient.post<{ data: DocumentRevisionSummary }>(
      `/documents/${documentId}/revisions`,
      label ? { label } : {},
    );
  },

  restore(documentId: string, revisionId: string) {
    return apiClient.post<{ data: unknown }>(
      `/documents/${documentId}/revisions/${revisionId}/restore`,
    );
  },
};

import { apiClient } from '@/shared/api';
import type { DocumentBlock } from '@/entities/block/model/types';
import type { DocumentResource } from '@/entities/resource/model/types';
import type {
  Document,
  DocumentListResponse,
  DocumentStatusResponse,
} from '../model/types';

export interface EditorPayload {
  document: Document;
  blocks: DocumentBlock[];
  resources: DocumentResource[];
}

export interface SaveDraftBlockPayload {
  id: string;
  type: string;
  sort: number;
  html?: string | null;
  styles?: Record<string, unknown> | null;
  meta?: Record<string, unknown> | null;
  assets?: Record<string, unknown> | null;
}

export const documentApi = {
  list(page = 1) {
    return apiClient.get<DocumentListResponse>('/documents', { params: { page } });
  },

  get(id: string) {
    return apiClient.get<{ data: Document }>(`/documents/${id}`);
  },

  status(id: string) {
    return apiClient.get<DocumentStatusResponse>(`/documents/${id}/status`);
  },

  editor(id: string) {
    return apiClient.get<EditorPayload>(`/documents/${id}/editor`);
  },

  upload(file: File, title?: string, translate = true) {
    const form = new FormData();
    form.append('file', file);
    if (title) {
      form.append('title', title);
    }
    form.append('translate', translate ? '1' : '0');
    return apiClient.post<{ data: Document }>('/documents', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  },

  saveDraft(id: string, blocks: SaveDraftBlockPayload[], createAutosaveCheckpoint = false) {
    return apiClient.put<{ data: Document }>(`/documents/${id}`, {
      blocks,
      create_autosave_checkpoint: createAutosaveCheckpoint,
    });
  },

  reprocess(id: string) {
    return apiClient.post<{ data: Document }>(`/documents/${id}/reprocess`);
  },

  remove(id: string) {
    return apiClient.delete(`/documents/${id}`);
  },

  exportHtmlUrl(id: string) {
    const base = import.meta.env.VITE_API_BASE_URL ?? '/api/v1';
    return `${base.replace(/\/$/, '')}/documents/${id}/export.html`;
  },

  translatedDocxUrl(id: string) {
    const base = import.meta.env.VITE_API_BASE_URL ?? '/api/v1';
    return `${base.replace(/\/$/, '')}/documents/${id}/translated.docx`;
  },
};

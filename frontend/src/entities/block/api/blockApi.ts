import { apiClient } from '@/shared/api';
import type { DocumentBlock } from '../model/types';
import type { Document } from '@/entities/document';

export const blockApi = {
  create(documentId: string, payload: Partial<DocumentBlock>) {
    return apiClient.post<{ data: DocumentBlock }>(
      `/documents/${documentId}/blocks`,
      payload,
    );
  },

  update(documentId: string, blockId: string, payload: Partial<DocumentBlock>) {
    return apiClient.put<{ data: DocumentBlock }>(
      `/documents/${documentId}/blocks/${blockId}`,
      payload,
    );
  },

  remove(documentId: string, blockId: string) {
    return apiClient.delete(`/documents/${documentId}/blocks/${blockId}`);
  },

  duplicate(documentId: string, blockId: string) {
    return apiClient.post<{ data: DocumentBlock }>(
      `/documents/${documentId}/blocks/${blockId}/duplicate`,
    );
  },

  reorder(documentId: string, orderedIds: string[]) {
    return apiClient.patch<{ data: Document }>(
      `/documents/${documentId}/blocks/reorder`,
      { ordered_ids: orderedIds },
    );
  },
};

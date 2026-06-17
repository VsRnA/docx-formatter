import { apiClient } from '@/shared/api';
import type { DocumentResource } from '../model/types';

export const resourceApi = {
  uploadImage(documentId: string, file: File) {
    const form = new FormData();
    form.append('file', file);
    return apiClient.post<{ data: DocumentResource }>(
      `/documents/${documentId}/images`,
      form,
      { headers: { 'Content-Type': 'multipart/form-data' } },
    );
  },
};

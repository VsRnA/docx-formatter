import { apiClient } from '@/shared/api';

export interface ReworkTextPayload {
  block_id?: string | null;
  text: string;
  prompt: string;
}

export interface ReworkTextResponse {
  text: string;
}

export const documentAiApi = {
  rework(documentId: string, payload: ReworkTextPayload) {
    return apiClient.post<ReworkTextResponse>(`/documents/${documentId}/ai/rework`, payload);
  },
};

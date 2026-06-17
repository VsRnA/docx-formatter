import axios from 'axios';
import { API_BASE_URL } from '@/shared/config/env';

export const apiClient = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    Accept: 'application/json',
  },
});

function extractApiErrorMessage(error: unknown): string {
  if (!axios.isAxiosError(error)) {
    return error instanceof Error ? error.message : 'Request failed';
  }

  const data = error.response?.data as
    | { message?: string; error?: string; errors?: Record<string, string[]> }
    | undefined;

  if (data?.errors) {
    const first = Object.values(data.errors).flat().find(Boolean);
    if (typeof first === 'string') {
      return first;
    }
  }

  return data?.message ?? data?.error ?? error.message ?? 'Request failed';
}

apiClient.interceptors.response.use(
  (response) => response,
  (error) => Promise.reject(new Error(extractApiErrorMessage(error))),
);

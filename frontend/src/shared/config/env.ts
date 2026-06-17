export const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? '/api/v1';

export const ROUTES = {
  home: '/',
  documentEdit: (id: string) => `/documents/${id}/edit`,
  documentPreview: (id: string) => `/documents/${id}/preview`,
} as const;

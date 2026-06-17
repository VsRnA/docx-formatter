export interface DocumentResource {
  id: string;
  document_id: string;
  type: string;
  storage_key: string;
  url: string | null;
  mime_type: string | null;
  size: number | null;
  meta_json: Record<string, unknown> | null;
}

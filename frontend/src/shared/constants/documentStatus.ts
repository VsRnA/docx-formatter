export const TERMINAL_DOCUMENT_STATUSES = ['ready', 'draft', 'published', 'failed'] as const;

export type TerminalDocumentStatus = (typeof TERMINAL_DOCUMENT_STATUSES)[number];

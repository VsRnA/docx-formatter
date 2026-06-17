export const ProcessingStages = {
  QUEUED: 'queued',
  DOWNLOAD: 'download',
  PARSE: 'parse',
  VALIDATE: 'validate',
  NORMALIZE: 'normalize',
  TRANSLATE: 'translate',
  WRITE_DOCX: 'write_docx',
  BUILD_HTML: 'build_html',
  COMPLETED: 'completed',
  FAILED: 'failed',
} as const;

/** Strip outer `<article class="document-root">` when assembling page shell. */
export function unwrapDocumentRootHtml(html: string): string {
  const trimmed = html.trim();
  const match = trimmed.match(/^<article\b[^>]*class="[^"]*\bdocument-root\b[^"]*"[^>]*>([\s\S]*)<\/article>$/i);
  return match ? match[1].trim() : html;
}

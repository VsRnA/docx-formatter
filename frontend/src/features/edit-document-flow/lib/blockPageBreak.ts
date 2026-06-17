import type { DocumentBlock } from '@/entities/block';

export const PAGE_BREAK_BEFORE_ATTR = 'data-page-break-before';
export const PAGE_BREAK_BEFORE_CLASS = 'doc-flow-block--page-break-before';
export const PDF_PAGE_BREAK_BEFORE_CLASS = 'doc-block--page-break-before';

export function hasPageBreakBefore(block: Pick<DocumentBlock, 'meta_json'>): boolean {
  return Boolean(block.meta_json?.page_break_before);
}

export function applyPageBreakBeforeToElement(element: HTMLElement, enabled: boolean): void {
  if (enabled) {
    element.dataset.pageBreakBefore = 'true';
    element.classList.add(PAGE_BREAK_BEFORE_CLASS, PDF_PAGE_BREAK_BEFORE_CLASS);
    return;
  }

  delete element.dataset.pageBreakBefore;
  element.classList.remove(PAGE_BREAK_BEFORE_CLASS, PDF_PAGE_BREAK_BEFORE_CLASS);
}

export function readPageBreakBeforeFromElement(element: HTMLElement | undefined): boolean {
  return element?.dataset.pageBreakBefore === 'true';
}

export function mergeBlockMetaWithPageBreak(
  block: DocumentBlock,
  wrapper: HTMLElement | undefined,
): Record<string, unknown> | null {
  const meta = { ...(block.meta_json ?? {}) };
  const enabled = readPageBreakBeforeFromElement(wrapper);

  if (enabled) {
    meta.page_break_before = true;
  } else {
    delete meta.page_break_before;
  }

  return Object.keys(meta).length > 0 ? meta : null;
}

import type { DocumentBlock } from '@/entities/block';
import type { SaveDraftBlockPayload } from '@/entities/document';
import {
  applyPageBreakBeforeToElement,
  hasPageBreakBefore,
  mergeBlockMetaWithPageBreak,
  PAGE_BREAK_BEFORE_CLASS,
} from './blockPageBreak';

/** Re-apply figure gallery geometry from OOXML data attributes (not flex/HTML heuristics). */
export function applyOoxmlPositionedGalleryLayout(html: string): string {
  if (!html || typeof document === 'undefined') {
    return html;
  }

  // doc-figure-canvas is fully positioned by the backend parser — re-applying from
  // data-ooxml-* would overwrite gap-resolved coordinates with stale raw OOXML values.
  if (!html.includes('doc-figure-gallery--positioned')) {
    return html;
  }

  const container = document.createElement('div');
  container.innerHTML = html;

  container.querySelectorAll<HTMLElement>('.doc-figure-gallery--positioned').forEach((gallery) => {
    const canvas = gallery.querySelector<HTMLElement>('.doc-figure-canvas__layer, .doc-figure-gallery__canvas');
    const captions = gallery.querySelector<HTMLElement>('.doc-figure-canvas__captions, .doc-figure-gallery__captions');
    if (!canvas) {
      return;
    }

    const figures = Array.from(canvas.querySelectorAll<HTMLElement>('figure.doc-image--inline'));
    const maxImageHeight = figures.reduce((max, figure) => {
      const height = readOoxmlPx(figure, 'height');
      return Math.max(max, height);
    }, 0);

    figures.forEach((figure) => {
      const left = readOoxmlPx(figure, 'left');
      const width = readOoxmlPx(figure, 'width');
      const height = readOoxmlPx(figure, 'height');
      const explicitTop = readOoxmlPx(figure, 'top');
      const top =
        explicitTop > 0 ? explicitTop : Math.max(0, maxImageHeight - height);

      figure.style.position = 'absolute';
      figure.style.left = `${left}px`;
      figure.style.top = `${top}px`;
      figure.style.margin = '0';
      figure.style.zIndex = '0';

      if (width > 0) {
        figure.dataset.ooxmlWidth = String(width);
      }
      if (left >= 0) {
        figure.dataset.ooxmlLeft = String(left);
      }
    });

    if (captions) {
      const captionCells = Array.from(
        captions.querySelectorAll<HTMLElement>('.doc-figure-caption-cell'),
      );

      figures.forEach((figure, index) => {
        const cell = captionCells[index];
        if (!cell) {
          return;
        }

        const left = readOoxmlPx(figure, 'left');
        const width = readOoxmlPx(figure, 'width');

        cell.style.position = 'absolute';
        cell.style.left = `${left}px`;
        cell.style.top = '0';
        cell.style.margin = '0';
        if (width > 0) {
          cell.style.width = `${width}px`;
        }
      });
    }

    gallery.style.display = 'block';
    gallery.style.position = 'relative';
  });

  return container.innerHTML;
}

function readOoxmlPx(element: HTMLElement, name: 'left' | 'top' | 'width' | 'height'): number {
  const fromAttribute = element.getAttribute(`data-ooxml-${name}`);
  if (fromAttribute && /^\d+$/.test(fromAttribute)) {
    return Number(fromAttribute);
  }

  const styleValue = element.style.getPropertyValue(name);
  const match = styleValue.trim().match(/^(\d+)px$/);
  if (match) {
    return Number(match[1]);
  }

  return 0;
}

/** Browsers break <p><div>…</div></p> — lift to <div> so block HTML stays intact in contentEditable. */
/** Browsers hoist <figcaption> out of <div> — convert legacy wraps to <figure>. */
export function repairFigureGalleryHtml(html: string): string {
  if (!html || typeof document === 'undefined') {
    return html;
  }

  if (!html.includes('doc-figure-gallery') || !html.includes('doc-figure-cell-wrap')) {
    return html;
  }

  const container = document.createElement('div');
  container.innerHTML = html;

  container.querySelectorAll<HTMLElement>('.doc-figure-cell-wrap').forEach((wrap) => {
    const figure = document.createElement('figure');
    figure.className = 'doc-figure-cell';
    for (const attr of Array.from(wrap.attributes)) {
      if (attr.name === 'class') {
        continue;
      }
      figure.setAttribute(attr.name, attr.value);
    }

    while (wrap.firstChild) {
      figure.appendChild(wrap.firstChild);
    }

    wrap.replaceWith(figure);
  });

  return container.innerHTML;
}

export function repairBlockWrapperHtml(html: string): string {
  return applyOoxmlPositionedGalleryLayout(
    repairFigureGalleryHtml(
      repairSymbolRowTextboxLayout(stripUnsupportedFigures(stripInvalidParagraphWrapper(html))),
    ),
  );
}

function stripInvalidParagraphWrapper(html: string): string {
  const trimmed = html.trim();
  const match = trimmed.match(/^<p(\s[^>]*)?>([\s\S]*)<\/p>$/i);
  if (!match) {
    return html;
  }

  if (!/<div\b/i.test(match[2])) {
    return html;
  }

  const attrs = match[1] ?? '';
  return `<div${attrs}>${match[2]}</div>`;
}

export function stripUnsupportedFigures(html: string): string {
  if (!html || typeof document === 'undefined') {
    return html;
  }

  if (!html.includes('doc-image--unsupported') && !html.includes('data-unsupported-format')) {
    return html;
  }

  const container = document.createElement('div');
  container.innerHTML = html;
  container.querySelectorAll('figure.doc-image--unsupported').forEach((figure) => figure.remove());

  return container.innerHTML;
}

/** Inline OOXML anchor offsets inside symbol rows clip the first characters. */
export function repairSymbolRowTextboxLayout(html: string): string {
  if (!html || typeof document === 'undefined') {
    return html;
  }

  if (!html.includes('doc-symbol-row') || !html.includes('doc-textbox')) {
    return html;
  }

  const container = document.createElement('div');
  container.innerHTML = html;

  container.querySelectorAll<HTMLElement>('.doc-symbol-row .doc-textbox').forEach((textbox) => {
    textbox.classList.remove('doc-textbox--anchored');

    for (const property of ['position', 'left', 'top', 'right', 'bottom', 'z-index']) {
      textbox.style.removeProperty(property);
    }

    if (!textbox.style.flex) {
      textbox.style.flex = '1 1 auto';
    }

    if (!textbox.style.minWidth) {
      textbox.style.minWidth = '0';
    }

    textbox.style.whiteSpace = 'normal';
  });

  return container.innerHTML;
}

export function mergeBlocksToEditorHtml(blocks: DocumentBlock[]): string {
  return blocks
    .map((block) => {
      const pageBreakAttrs = hasPageBreakBefore(block)
        ? ` data-page-break-before="true" class="doc-block doc-flow-block doc-block--page-break-before ${PAGE_BREAK_BEFORE_CLASS}"`
        : ' class="doc-block doc-flow-block"';

      const html = repairBlockWrapperHtml(block.html ?? '');

      return `<div data-block-id="${block.id}" data-block-type="${block.type}"${pageBreakAttrs}>${html}</div>`;
    })
    .join('');
}

export function extractBlockUpdates(
  container: HTMLElement,
  blocks: DocumentBlock[],
): SaveDraftBlockPayload[] {
  const wrappers = Array.from(
    container.querySelectorAll<HTMLElement>(':scope > [data-block-id]'),
  );

  return blocks
    .map((block) => {
      const wrapper = wrappers.find((element) => element.dataset.blockId === block.id);
      const domIndex = wrapper ? wrappers.indexOf(wrapper) : -1;

      return {
        id: block.id,
        type: block.type,
        sort: domIndex >= 0 ? domIndex : block.sort,
        html: wrapper ? wrapper.innerHTML : block.html,
        styles: block.styles_json,
        meta: mergeBlockMetaWithPageBreak(block, wrapper),
        assets: block.assets_json,
      };
    })
    .sort((a, b) => a.sort - b.sort);
}

function createBlockWrapper(block: DocumentBlock): HTMLDivElement {
  const wrapper = document.createElement('div');
  wrapper.dataset.blockId = block.id;
  wrapper.dataset.blockType = block.type;
  wrapper.className = 'doc-block doc-flow-block';
  wrapper.innerHTML = repairBlockWrapperHtml(block.html ?? '');
  applyPageBreakBeforeToElement(wrapper, hasPageBreakBefore(block));
  return wrapper;
}

export function setBlockPageBreakBefore(
  container: HTMLElement,
  blockId: string,
  enabled: boolean,
): void {
  const wrapper = container.querySelector<HTMLElement>(`[data-block-id="${blockId}"]`);
  if (!wrapper) {
    return;
  }

  applyPageBreakBeforeToElement(wrapper, enabled);
}

export function appendBlockToEditor(container: HTMLElement, block: DocumentBlock): void {
  container.appendChild(createBlockWrapper(block));
}

export function insertBlockAfter(
  container: HTMLElement,
  afterBlockId: string | null,
  block: DocumentBlock,
): void {
  const wrapper = createBlockWrapper(block);

  if (!afterBlockId) {
    container.appendChild(wrapper);
    return;
  }

  const after = container.querySelector<HTMLElement>(`[data-block-id="${afterBlockId}"]`);
  if (after?.nextSibling) {
    container.insertBefore(wrapper, after.nextSibling);
    return;
  }

  if (after) {
    container.appendChild(wrapper);
    return;
  }

  container.appendChild(wrapper);
}

export function removeBlockFromEditor(container: HTMLElement, blockId: string): void {
  container.querySelector(`[data-block-id="${blockId}"]`)?.remove();
}

export function reorderBlocksInEditor(container: HTMLElement, orderedIds: string[]): void {
  orderedIds.forEach((blockId) => {
    const element = container.querySelector(`[data-block-id="${blockId}"]`);
    if (element) {
      container.appendChild(element);
    }
  });
}

export function scrollToBlock(container: HTMLElement, blockId: string): void {
  const wrapper = container.querySelector<HTMLElement>(`[data-block-id="${blockId}"]`);
  wrapper?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  wrapper?.classList.add('doc-flow-block--highlight');
  window.setTimeout(() => wrapper?.classList.remove('doc-flow-block--highlight'), 1200);
}

export function updateBlockInEditor(container: HTMLElement, blockId: string, html: string): void {
  const wrapper = container.querySelector<HTMLElement>(`[data-block-id="${blockId}"]`);
  if (wrapper) {
    wrapper.innerHTML = html;
  }
}

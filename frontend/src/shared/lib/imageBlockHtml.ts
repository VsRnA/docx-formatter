export type ImageAlign = 'left' | 'center' | 'right';
export type ImageWrap = 'inline' | 'square' | 'tight';

export function parseImageBlock(html: string | null): {
  src: string;
  alt: string;
  align: ImageAlign;
  wrap: ImageWrap;
  caption: string;
} {
  if (!html) {
    return { src: '', alt: '', align: 'left', wrap: 'inline', caption: '' };
  }

  const doc = new DOMParser().parseFromString(html, 'text/html');
  const figure = doc.querySelector('figure.doc-image');
  const img = doc.querySelector('img');
  const captionEl = figure?.querySelector(':scope > figcaption');
  const caption =
    captionEl && !captionEl.classList.contains('doc-image__unsupported-caption')
      ? captionEl.textContent?.trim() ?? ''
      : '';
  const style = figure?.getAttribute('style') ?? '';
  const className = figure?.className ?? '';

  let align: ImageAlign = 'left';
  if (style.includes('center') || className.includes('ql-align-center')) align = 'center';
  if (style.includes('right') || className.includes('ql-align-right')) align = 'right';

  let wrap: ImageWrap = 'inline';
  if (className.includes('doc-image--wrap-square')) wrap = 'square';
  if (className.includes('doc-image--wrap-tight')) wrap = 'tight';

  return {
    src: img?.getAttribute('src') ?? '',
    alt: img?.getAttribute('alt') ?? '',
    align,
    wrap,
    caption,
  };
}

export function resolveImageUrl(
  html: string | null | undefined,
  assets?: Record<string, unknown> | null,
): string {
  const fromAssets = typeof assets?.url === 'string' ? assets.url.trim() : '';
  if (fromAssets) {
    return fromAssets;
  }

  return parseImageBlock(html ?? null).src.trim();
}

function setFigureAlign(figure: HTMLElement, align: ImageAlign): void {
  figure.style.removeProperty('text-align');
  figure.classList.remove('ql-align-center', 'ql-align-right', 'ql-align-left');

  if (align === 'center') {
    figure.style.textAlign = 'center';
    figure.classList.add('ql-align-center');
  } else if (align === 'right') {
    figure.style.textAlign = 'right';
    figure.classList.add('ql-align-right');
  }
}

function setFigureWrap(figure: HTMLElement, wrap: ImageWrap): void {
  figure.classList.remove('doc-image--wrap-square', 'doc-image--wrap-tight');

  if (wrap === 'square') {
    figure.classList.add('doc-image--wrap-square');
  } else if (wrap === 'tight') {
    figure.classList.add('doc-image--wrap-tight');
  }
}

function setFigureCaption(figure: HTMLElement, caption: string): void {
  const existing = figure.querySelector(':scope > figcaption');

  if (!caption.trim()) {
    existing?.remove();
    return;
  }

  const target = existing ?? document.createElement('figcaption');
  target.textContent = caption;
  if (!existing) {
    figure.appendChild(target);
  }
}

export function patchImageBlockHtml(
  html: string | null,
  patch: {
    src?: string;
    alt?: string;
    align?: ImageAlign;
    wrap?: ImageWrap;
    caption?: string;
  },
): string {
  const parsed = parseImageBlock(html);
  const src = (patch.src ?? parsed.src).trim();

  if (!src) {
    return html ?? '';
  }

  if (html && html.includes('figure') && typeof document !== 'undefined') {
    const container = document.createElement('div');
    container.innerHTML = html;
    const figure = container.querySelector<HTMLElement>('figure.doc-image, figure');
    const img = figure?.querySelector<HTMLImageElement>('img');

    if (figure && img) {
      img.setAttribute('src', src);

      if (patch.alt !== undefined) {
        img.setAttribute('alt', patch.alt);
      }

      if (patch.align !== undefined) {
        setFigureAlign(figure, patch.align);
      }

      if (patch.wrap !== undefined) {
        setFigureWrap(figure, patch.wrap);
      }

      if (patch.caption !== undefined) {
        setFigureCaption(figure, patch.caption);
      }

      return figure.outerHTML;
    }
  }

  return buildImageHtml(
    src,
    patch.alt ?? parsed.alt,
    patch.align ?? parsed.align,
    false,
    patch.wrap ?? parsed.wrap,
    patch.caption ?? parsed.caption,
  );
}

export function buildImageHtml(
  src: string,
  alt: string,
  align: ImageAlign,
  pending = false,
  wrap: ImageWrap = 'inline',
  caption = '',
): string {
  if (!src || pending) {
    return '<figure class="doc-image doc-image--pending"><div class="doc-image__placeholder"><span>Нажмите, чтобы загрузить изображение</span></div></figure>';
  }

  const alignStyle =
    align === 'center' ? 'text-align:center;' : align === 'right' ? 'text-align:right;' : '';
  const wrapClass =
    wrap === 'square'
      ? ' doc-image--wrap-square'
      : wrap === 'tight'
        ? ' doc-image--wrap-tight'
        : '';
  const captionHtml = caption
    ? `<figcaption>${caption.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</figcaption>`
    : '';

  return `<figure class="doc-image${wrapClass}" style="${alignStyle}"><img src="${src.replace(/"/g, '&quot;')}" alt="${alt.replace(/"/g, '&quot;')}" />${captionHtml}</figure>`;
}

export function applyImageAlign(html: string | null, align: ImageAlign): string {
  const parsed = parseImageBlock(html);
  if (!parsed.src) {
    return html ?? '';
  }

  return patchImageBlockHtml(html, { align });
}

export function applyImageWrap(html: string | null, wrap: ImageWrap): string {
  const parsed = parseImageBlock(html);
  if (!parsed.src) {
    return html ?? '';
  }

  return patchImageBlockHtml(html, { wrap });
}

export function applyImageCaption(html: string | null, caption: string): string {
  const parsed = parseImageBlock(html);
  if (!parsed.src) {
    return html ?? '';
  }

  return patchImageBlockHtml(html, { caption });
}

export type ImageAlign = 'left' | 'center' | 'right';

export function parseImageBlock(html: string | null): { src: string; alt: string; align: ImageAlign } {
  if (!html) {
    return { src: '', alt: '', align: 'left' };
  }

  const doc = new DOMParser().parseFromString(html, 'text/html');
  const figure = doc.querySelector('figure.doc-image');
  const img = doc.querySelector('img');
  const style = figure?.getAttribute('style') ?? '';

  let align: ImageAlign = 'left';
  if (style.includes('center')) align = 'center';
  if (style.includes('right')) align = 'right';

  return {
    src: img?.getAttribute('src') ?? '',
    alt: img?.getAttribute('alt') ?? '',
    align,
  };
}

export function buildImageHtml(
  src: string,
  alt: string,
  align: ImageAlign,
  pending = false,
): string {
  if (!src || pending) {
    return '<figure class="doc-image doc-image--pending"><div class="doc-image__placeholder"><span>Нажмите, чтобы загрузить изображение</span></div></figure>';
  }

  const alignStyle =
    align === 'center' ? 'text-align:center;' : align === 'right' ? 'text-align:right;' : '';

  return `<figure class="doc-image" style="${alignStyle}"><img src="${src}" alt="${alt.replace(/"/g, '&quot;')}" /></figure>`;
}

export function applyImageAlign(html: string | null, align: ImageAlign): string {
  const parsed = parseImageBlock(html);
  return buildImageHtml(parsed.src, parsed.alt, align, !parsed.src);
}

const ALIGN_CLASSES: Record<string, string> = {
  'ql-align-center': 'center',
  'ql-align-right': 'right',
  'ql-align-justify': 'justify',
};

/**
 * Converts Quill class-based formatting to inline styles for portable HTML output.
 */
export function normalizeEditorHtml(html: string): string {
  if (!html || typeof document === 'undefined') {
    return html;
  }

  const container = document.createElement('div');
  container.innerHTML = html;

  container.querySelectorAll<HTMLElement>('[class]').forEach((element) => {
    for (const className of [...element.classList]) {
      const align = ALIGN_CLASSES[className];
      if (align) {
        element.style.textAlign = align;
        element.classList.remove(className);
      }

      if (className.startsWith('ql-color-')) {
        const color = className.replace('ql-color-', '').replace(/_/g, '');
        element.style.color = color.startsWith('#') ? color : `#${color}`;
        element.classList.remove(className);
      }

      if (className.startsWith('ql-bg-')) {
        const color = className.replace('ql-bg-', '').replace(/_/g, '');
        element.style.backgroundColor = color.startsWith('#') ? color : `#${color}`;
        element.classList.remove(className);
      }
    }

    if (element.classList.length === 0) {
      element.removeAttribute('class');
    }
  });

  return container.innerHTML;
}

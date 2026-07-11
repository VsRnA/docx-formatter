export interface OutlineEntry {
  blockId: string;
  level: 2 | 3;
  text: string;
}

export function collectOutlineFromEditor(editor: HTMLElement | null): OutlineEntry[] {
  if (!editor) {
    return [];
  }

  const entries: OutlineEntry[] = [];

  editor.querySelectorAll<HTMLElement>('[data-block-id]').forEach((wrapper) => {
    const blockId = wrapper.dataset.blockId;
    if (!blockId) {
      return;
    }

    const heading = wrapper.querySelector('h2, h3');
    if (!heading) {
      return;
    }

    const tag = heading.tagName.toLowerCase();
    if (tag !== 'h2' && tag !== 'h3') {
      return;
    }

    const text = heading.textContent?.trim() ?? '';
    if (text === '') {
      return;
    }

    entries.push({
      blockId,
      level: tag === 'h2' ? 2 : 3,
      text,
    });
  });

  return entries;
}

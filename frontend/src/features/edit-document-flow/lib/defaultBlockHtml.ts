import type { BlockType } from '@/entities/block';

const DEFAULT_HTML: Record<string, string> = {
  heading: '<h2>Новый заголовок</h2>',
  heading2: '<h2>Новый заголовок</h2>',
  heading3: '<h3>Новый подзаголовок</h3>',
  paragraph: '<p></p>',
  list: '<ul class="doc-list doc-list--disc"><li></li></ul>',
  table: '<table class="doc-table"><tbody><tr><td></td><td></td></tr><tr><td></td><td></td></tr></tbody></table>',
  image:
    '<figure class="doc-image doc-image--pending"><div class="doc-image__placeholder"><span>Нажмите, чтобы загрузить изображение</span></div></figure>',
};

export function defaultBlockHtml(type: BlockType | 'heading2' | 'heading3'): string {
  return DEFAULT_HTML[type] ?? '<p></p>';
}

export function defaultTableHtml(rows = 2, cols = 2, withHeaderRow = false): string {
  const body = Array.from({ length: rows }, (_, rowIndex) => {
    const cells = Array.from({ length: cols }, () => {
      if (withHeaderRow && rowIndex === 0) {
        return '<th></th>';
      }

      return '<td></td>';
    }).join('');

    return `<tr>${cells}</tr>`;
  }).join('');

  return `<table class="doc-table"><tbody>${body}</tbody></table>`;
}

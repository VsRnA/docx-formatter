import type { BlockType } from '@/entities/block';

const DEFAULT_HTML: Record<string, string> = {
  heading: '<h2>Новый заголовок</h2>',
  paragraph: '<p></p>',
  list: '<ul class="doc-list doc-list--disc"><li></li></ul>',
  image:
    '<figure class="doc-image doc-image--pending"><div class="doc-image__placeholder"><span>Нажмите, чтобы загрузить изображение</span></div></figure>',
};

export function defaultBlockHtml(type: BlockType): string {
  return DEFAULT_HTML[type] ?? '<p></p>';
}

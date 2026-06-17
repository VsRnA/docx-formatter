import type { BlockType } from '@/entities/block';

export const BLOCK_TYPE_LABELS: Record<BlockType, string> = {
  heading: 'Заголовок',
  paragraph: 'Параграф',
  list: 'Список',
  table: 'Таблица',
  image: 'Изображение',
  caption: 'Подпись',
  image_text: 'Текст на изображении',
  link_block: 'Ссылка',
  html_raw: 'Неизвестный OOXML',
  formula: 'Формула',
};

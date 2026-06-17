import { DOCX_EXTENSION, MAX_UPLOAD_MB } from '@/shared/constants';

const DOCX_SUFFIX = `.${DOCX_EXTENSION}`;

export function validateDocxFile(file: File): string | null {
  const name = file.name.trim().toLowerCase();

  if (!name.endsWith(DOCX_SUFFIX)) {
    const extension = name.includes('.') ? name.slice(name.lastIndexOf('.')) : '';
    return extension
      ? `Формат ${extension} не поддерживается. Загрузите файл .docx`
      : 'Допустим только файл .docx';
  }

  if (name === DOCX_SUFFIX || name.endsWith(`/${DOCX_SUFFIX}`)) {
    return 'Укажите корректное имя файла с расширением .docx';
  }

  if (file.size > MAX_UPLOAD_MB * 1024 * 1024) {
    return `Размер файла не должен превышать ${MAX_UPLOAD_MB} МБ`;
  }

  return null;
}

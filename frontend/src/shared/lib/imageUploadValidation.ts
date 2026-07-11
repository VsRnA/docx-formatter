const MAX_IMAGE_BYTES = 10 * 1024 * 1024;
const UNSUPPORTED_IMAGE_EXTENSIONS = new Set(['emf', 'wmf']);

export function getImageUploadError(file: File): string | null {
  const extension = file.name.split('.').pop()?.toLowerCase() ?? '';

  if (UNSUPPORTED_IMAGE_EXTENSIONS.has(extension)) {
    return 'Формат EMF/WMF не поддерживается браузером';
  }

  if (file.size > MAX_IMAGE_BYTES) {
    return 'Файл больше 10 МБ';
  }

  if (!file.type.startsWith('image/')) {
    return 'Можно загружать только изображения';
  }

  return null;
}

export function isSupportedImageFile(file: File): boolean {
  return getImageUploadError(file) === null;
}

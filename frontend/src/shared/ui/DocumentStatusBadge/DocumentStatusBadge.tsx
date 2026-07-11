import './DocumentStatusBadge.css';

const STATUS_LABELS: Record<string, string> = {
  uploading: 'Загрузка',
  processing: 'Обработка',
  ready: 'Готов',
  failed: 'Ошибка',
  draft: 'Черновик',
  published: 'Опубликован',
};

interface Props {
  status: string;
  className?: string;
}

export function DocumentStatusBadge({ status, className }: Props) {
  const key = status in STATUS_LABELS ? status : 'draft';
  const label = STATUS_LABELS[key] ?? status;

  return (
    <span
      className={`document-status-badge document-status-badge--${key}${className ? ` ${className}` : ''}`}
    >
      <span className="document-status-badge__dot" aria-hidden="true" />
      <span className="document-status-badge__label">{label}</span>
    </span>
  );
}

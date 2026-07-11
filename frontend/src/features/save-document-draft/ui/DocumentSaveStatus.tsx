import { Tag } from 'antd';
import type { AutosaveStatus } from '../model/useAutosaveDraft';
import './DocumentSaveStatus.css';

interface Props {
  status: AutosaveStatus;
}

const LABELS: Record<AutosaveStatus, string> = {
  saved: 'Все изменения сохранены',
  saving: 'Сохранение…',
  dirty: 'Есть несохранённые изменения',
  error: 'Ошибка сохранения',
};

const COLORS: Record<AutosaveStatus, string> = {
  saved: 'success',
  saving: 'processing',
  dirty: 'warning',
  error: 'error',
};

export function DocumentSaveStatus({ status }: Props) {
  return (
    <Tag color={COLORS[status]} className="document-save-status">
      {LABELS[status]}
    </Tag>
  );
}

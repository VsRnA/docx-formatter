import { Typography } from 'antd';
import { useEffect, useState } from 'react';
import type { OutlineEntry } from '../lib/collectOutline';
import { collectOutlineFromEditor } from '../lib/collectOutline';
import './DocumentOutlineSidebar.css';

interface Props {
  editorRoot: HTMLElement | null;
  onNavigate: (blockId: string) => void;
  refreshKey?: number;
}

export function DocumentOutlineSidebar({ editorRoot, onNavigate, refreshKey = 0 }: Props) {
  const [entries, setEntries] = useState<OutlineEntry[]>([]);

  useEffect(() => {
    if (!editorRoot) {
      setEntries([]);
      return;
    }

    const update = () => {
      setEntries(collectOutlineFromEditor(editorRoot));
    };

    update();

    let timer: ReturnType<typeof setTimeout> | null = null;
    const scheduleUpdate = () => {
      if (timer) {
        clearTimeout(timer);
      }
      timer = setTimeout(update, 200);
    };

    editorRoot.addEventListener('input', scheduleUpdate);
    editorRoot.addEventListener('keyup', scheduleUpdate);

    return () => {
      if (timer) {
        clearTimeout(timer);
      }
      editorRoot.removeEventListener('input', scheduleUpdate);
      editorRoot.removeEventListener('keyup', scheduleUpdate);
    };
  }, [editorRoot, refreshKey]);

  return (
    <div className="document-outline-sidebar">
      <Typography.Text strong className="document-outline-sidebar__title">
        Навигация
      </Typography.Text>
      {entries.length === 0 ? (
        <Typography.Text type="secondary" className="document-outline-sidebar__empty">
          Заголовки не найдены
        </Typography.Text>
      ) : (
        <ul className="document-outline-sidebar__list">
          {entries.map((entry) => (
            <li
              key={`${entry.blockId}-${entry.text}`}
              className={`document-outline-sidebar__item document-outline-sidebar__item--h${entry.level}`}
            >
              <button type="button" onClick={() => onNavigate(entry.blockId)}>
                {entry.text}
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

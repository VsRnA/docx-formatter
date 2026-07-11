import { Typography } from 'antd';
import type { DocumentResource } from '@/entities/resource';
import './UnplacedImagesSidebar.css';

interface Props {
  resources: DocumentResource[];
  onInsert: (resource: DocumentResource) => void;
}

export function UnplacedImagesSidebar({ resources, onInsert }: Props) {
  return (
    <div className="unplaced-images-sidebar">
      <Typography.Text strong className="unplaced-images-sidebar__title">
        Изображения
      </Typography.Text>
      {resources.length === 0 ? (
        <Typography.Text type="secondary" className="unplaced-images-sidebar__empty">
          Нет неразмещённых изображений
        </Typography.Text>
      ) : (
        <div className="unplaced-images-sidebar__grid">
          {resources.map((resource) => (
            <button
              key={resource.id}
              type="button"
              className="unplaced-images-sidebar__item"
              onClick={() => onInsert(resource)}
              title="Вставить в документ"
            >
              {resource.url ? (
                <img src={resource.url} alt="" />
              ) : (
                <span>Нет превью</span>
              )}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

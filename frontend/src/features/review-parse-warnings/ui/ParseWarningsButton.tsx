import { Badge, Button, type ButtonProps, List, Popover, Typography } from 'antd';
import { WarningOutlined } from '@ant-design/icons';
import type { ParseWarningSummary } from '@/entities/document';

interface Props {
  warnings?: ParseWarningSummary[];
  size?: ButtonProps['size'];
}

const WARNING_LABELS: Record<string, string> = {
  image_unsupported_format: 'Изображения EMF/WMF в исходном DOCX (пропущены при парсинге)',
  image_decorative_filtered: 'Декоративные изображения пропущены',
  image_extract_failed: 'Не удалось извлечь изображение',
  unknown: 'Прочие предупреждения',
};

function labelFor(type: string): string {
  return WARNING_LABELS[type] ?? type;
}

export function ParseWarningsButton({ warnings, size }: Props) {
  if (!warnings || warnings.length === 0) {
    return null;
  }

  const total = warnings.reduce((sum, warning) => sum + warning.count, 0);

  const content = (
    <div style={{ maxWidth: 360 }}>
      <Typography.Paragraph type="secondary" style={{ marginBottom: 8 }}>
        При разборе документа часть содержимого обработана с оговорками:
      </Typography.Paragraph>
      <List
        size="small"
        dataSource={warnings}
        renderItem={(warning) => (
          <List.Item>
            <span>{labelFor(warning.type)}</span>
            <Badge count={warning.count} style={{ backgroundColor: '#faad14' }} />
          </List.Item>
        )}
      />
    </div>
  );

  return (
    <Popover content={content} title="Предупреждения разбора" trigger="click" placement="bottomRight">
      <Badge count={total} size="small" offset={[-2, 2]}>
        <Button size={size} icon={<WarningOutlined />}>
          Предупреждения
        </Button>
      </Badge>
    </Popover>
  );
}

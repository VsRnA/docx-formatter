import { useMemo, useState } from 'react';
import { Button, type ButtonProps, Drawer, Empty, Segmented, Space, Tag, Typography } from 'antd';
import { TranslationOutlined } from '@ant-design/icons';
import type { DocumentBlock, TranslationStatus } from '@/entities/block';

interface Props {
  blocks: DocumentBlock[];
  size?: ButtonProps['size'];
}

type Filter = 'all' | 'failed' | 'skipped';

const STATUS_TAG: Record<TranslationStatus, { color: string; label: string }> = {
  pending: { color: 'default', label: 'Ожидает' },
  done: { color: 'green', label: 'Переведено' },
  failed: { color: 'red', label: 'Ошибка' },
  skipped: { color: 'gold', label: 'Пропущено' },
};

function isTranslatable(block: DocumentBlock): boolean {
  return Boolean((block.text_original ?? '').trim());
}

export function TranslationReviewButton({ blocks, size }: Props) {
  const [open, setOpen] = useState(false);
  const [filter, setFilter] = useState<Filter>('all');

  const translatable = useMemo(() => blocks.filter(isTranslatable), [blocks]);

  const counts = useMemo(
    () => ({
      failed: translatable.filter((b) => b.translation_status === 'failed').length,
      skipped: translatable.filter((b) => b.translation_status === 'skipped').length,
    }),
    [translatable],
  );

  const visible = useMemo(() => {
    if (filter === 'all') {
      return translatable;
    }
    return translatable.filter((b) => b.translation_status === filter);
  }, [translatable, filter]);

  return (
    <>
      <Button size={size} icon={<TranslationOutlined />} onClick={() => setOpen(true)}>
        Перевод
        {counts.failed > 0 ? <Tag color="red" style={{ marginLeft: 6 }}>{counts.failed}</Tag> : null}
      </Button>
      <Drawer
        title="Ревью перевода"
        open={open}
        onClose={() => setOpen(false)}
        width={560}
      >
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
          <Segmented
            value={filter}
            onChange={(value) => setFilter(value as Filter)}
            options={[
              { label: `Все (${translatable.length})`, value: 'all' },
              { label: `Ошибки (${counts.failed})`, value: 'failed' },
              { label: `Пропущено (${counts.skipped})`, value: 'skipped' },
            ]}
          />

          {visible.length === 0 ? (
            <Empty description="Нет сегментов" />
          ) : (
            visible.map((block) => {
              const tag = STATUS_TAG[block.translation_status] ?? STATUS_TAG.pending;
              return (
                <div
                  key={block.id}
                  style={{ border: '1px solid #f0f0f0', borderRadius: 6, padding: 12 }}
                >
                  <Space style={{ marginBottom: 8 }}>
                    <Tag color={tag.color}>{tag.label}</Tag>
                    <Typography.Text type="secondary">{block.type}</Typography.Text>
                  </Space>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                    <div>
                      <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                        Оригинал
                      </Typography.Text>
                      <Typography.Paragraph style={{ marginBottom: 0 }}>
                        {block.text_original}
                      </Typography.Paragraph>
                    </div>
                    <div>
                      <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                        Перевод
                      </Typography.Text>
                      <Typography.Paragraph
                        style={{ marginBottom: 0 }}
                        type={block.text_translated ? undefined : 'warning'}
                      >
                        {block.text_translated || 'Остался оригинал'}
                      </Typography.Paragraph>
                    </div>
                  </div>
                </div>
              );
            })
          )}
        </Space>
      </Drawer>
    </>
  );
}

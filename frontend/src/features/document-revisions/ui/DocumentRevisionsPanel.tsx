import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Button, Input, List, Modal, Space, Typography, message } from 'antd';
import { documentRevisionApi } from '@/entities/document';
import { mapRevisionBlocks } from '../lib/mapRevisionBlocks';
import { sortBlocks } from '@/entities/block';
import { formatDate } from '@/shared/lib/formatDate';
import './DocumentRevisionsPanel.css';

interface Props {
  documentId: string;
  documentTitle: string;
  open: boolean;
  onClose: () => void;
  onRestored: () => void | Promise<void>;
}

const TRIGGER_LABELS: Record<string, string> = {
  manual: 'Ручная версия',
  autosave_checkpoint: 'Автосохранение',
  pre_publish: 'Перед публикацией',
  pre_restore: 'Перед восстановлением',
};

function buildDefaultRevisionLabel(documentTitle: string): string {
  const title = documentTitle.trim() || 'Документ';
  return `${title} - ${formatDate(new Date().toISOString())}`;
}

export function DocumentRevisionsPanel({
  documentId,
  documentTitle,
  open,
  onClose,
  onRestored,
}: Props) {
  const queryClient = useQueryClient();
  const [previewId, setPreviewId] = useState<string | null>(null);
  const [label, setLabel] = useState('');

  const revisionsQuery = useQuery({
    queryKey: ['document-revisions', documentId],
    queryFn: () => documentRevisionApi.list(documentId).then((response) => response.data.data),
    enabled: open && Boolean(documentId),
  });

  const previewQuery = useQuery({
    queryKey: ['document-revision', documentId, previewId],
    queryFn: () =>
      documentRevisionApi.get(documentId, previewId ?? '').then((response) => response.data.data),
    enabled: open && Boolean(previewId),
  });

  const createRevision = useMutation({
    mutationFn: () => documentRevisionApi.create(documentId, label.trim() || undefined),
    onSuccess: () => {
      message.success('Версия сохранена');
      setLabel(buildDefaultRevisionLabel(documentTitle));
      void queryClient.invalidateQueries({ queryKey: ['document-revisions', documentId] });
    },
    onError: (error: Error) => message.error(error.message),
  });

  const restoreRevision = useMutation({
    mutationFn: (revisionId: string) => documentRevisionApi.restore(documentId, revisionId),
    onSuccess: async () => {
      message.success('Версия восстановлена');
      await onRestored();
      onClose();
    },
    onError: (error: Error) => message.error(error.message),
  });

  const handleAfterOpenChange = (nextOpen: boolean) => {
    if (nextOpen) {
      setLabel(buildDefaultRevisionLabel(documentTitle));
      setPreviewId(null);
    } else {
      setLabel('');
      setPreviewId(null);
    }
  };

  return (
    <Modal
      title="История версий"
      open={open}
      onCancel={onClose}
      afterOpenChange={handleAfterOpenChange}
      footer={null}
      width={760}
      destroyOnClose
    >
      <Space direction="vertical" size={16} style={{ width: '100%' }}>
        <Space.Compact style={{ width: '100%' }}>
          <Input
            placeholder="Метка версии (необязательно)"
            value={label}
            onChange={(event) => setLabel(event.target.value)}
          />
          <Button
            type="primary"
            loading={createRevision.isPending}
            onClick={() => createRevision.mutate()}
          >
            Сохранить версию
          </Button>
        </Space.Compact>

        <List
          loading={revisionsQuery.isLoading}
          dataSource={revisionsQuery.data ?? []}
          locale={{ emptyText: 'Версий пока нет' }}
          renderItem={(item) => (
            <List.Item
              actions={[
                <Button key="preview" size="small" onClick={() => setPreviewId(item.id)}>
                  Просмотр
                </Button>,
                <Button
                  key="restore"
                  size="small"
                  type="primary"
                  loading={restoreRevision.isPending}
                  onClick={() => restoreRevision.mutate(item.id)}
                >
                  Восстановить
                </Button>,
              ]}
            >
              <List.Item.Meta
                title={item.label || TRIGGER_LABELS[item.trigger] || item.trigger}
                description={`${new Date(item.created_at).toLocaleString('ru-RU')} · блоков: ${item.blocks_count}${
                  item.summary ? ` · ${item.summary}` : ''
                }`}
              />
            </List.Item>
          )}
        />

        {previewQuery.data ? (
          <div className="document-revisions-panel__preview">
            <Typography.Text strong>Предпросмотр версии</Typography.Text>
            <div
              className="document-root document-revisions-panel__preview-content"
              dangerouslySetInnerHTML={{
                __html:
                  previewQuery.data.html_draft_snapshot
                  || sortBlocks(mapRevisionBlocks(previewQuery.data.blocks_snapshot))
                      .map((block) => block.html ?? '')
                      .join(''),
              }}
            />
          </div>
        ) : null}
      </Space>
    </Modal>
  );
}

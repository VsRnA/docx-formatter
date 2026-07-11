import { useState } from 'react';
import {
  Button,
  Card,
  Dropdown,
  Empty,
  Modal,
  Spin,
  Tag,
  Tooltip,
  Typography,
  message,
} from 'antd';
import type { MenuProps } from 'antd';
import {
  DeleteOutlined,
  EyeOutlined,
  HistoryOutlined,
  MoreOutlined,
  ReloadOutlined,
} from '@ant-design/icons';
import { Link } from 'react-router-dom';
import { useMutation } from '@tanstack/react-query';
import type { Document } from '@/entities/document';
import { documentApi } from '@/entities/document';
import { formatDate } from '@/shared/lib/formatDate';
import { ROUTES } from '@/shared/config/env';
import { processingStageLabel } from '@/shared/constants';
import { DocumentStatusBadge } from '@/shared/ui/DocumentStatusBadge';
import { DocumentRevisionsPanel } from '@/features/document-revisions';
import './DocumentTable.css';

interface Props {
  documents: Document[];
  loading?: boolean;
  onChanged?: () => void;
}

function isEditableStatus(status: Document['status']): boolean {
  return status === 'ready' || status === 'draft' || status === 'published';
}

interface DocumentActionsProps {
  record: Document;
  onOpenRevisions: (record: Document) => void;
  onReprocess: (id: string) => void;
  onDelete: (record: Document) => void;
  reprocessPending: boolean;
}

function DocumentActions({
  record,
  onOpenRevisions,
  onReprocess,
  onDelete,
  reprocessPending,
}: DocumentActionsProps) {
  const isEditable = isEditableStatus(record.status);
  const menuItems: MenuProps['items'] = [
    {
      key: 'delete',
      danger: true,
      icon: <DeleteOutlined />,
      label: 'Удалить',
    },
  ];

  return (
    <div className="document-list__actions">
      {isEditable ? (
        <Link to={ROUTES.documentEdit(record.id)}>
          <Button type="primary" size="small">
            Открыть
          </Button>
        </Link>
      ) : null}
      {(record.status === 'processing' || record.status === 'failed') && (
        <Button
          type="primary"
          size="small"
          icon={<ReloadOutlined />}
          loading={reprocessPending}
          onClick={() => onReprocess(record.id)}
        >
          Перезапустить
        </Button>
      )}
      {isEditable ? (
        <>
          <Tooltip title="Черновой просмотр">
            <Link to={ROUTES.documentPreview(record.id)}>
              <Button type="text" size="small" icon={<EyeOutlined />} />
            </Link>
          </Tooltip>
          <Tooltip title="История версий">
            <Button
              type="text"
              size="small"
              icon={<HistoryOutlined />}
              onClick={() => onOpenRevisions(record)}
            />
          </Tooltip>
        </>
      ) : null}
      <Dropdown
        menu={{
          items: menuItems,
          onClick: ({ key }) => {
            if (key === 'delete') {
              onDelete(record);
            }
          },
        }}
        trigger={['click']}
      >
        <Button type="text" size="small" icon={<MoreOutlined />} />
      </Dropdown>
    </div>
  );
}

export function DocumentTable({ documents, loading, onChanged }: Props) {
  const [revisionsDoc, setRevisionsDoc] = useState<Document | null>(null);

  const reprocess = useMutation({
    mutationFn: (id: string) => documentApi.reprocess(id),
    onSuccess: () => {
      message.success('Обработка перезапущена');
      onChanged?.();
    },
    onError: (err: Error) => message.error(err.message),
  });

  const remove = useMutation({
    mutationFn: (id: string) => documentApi.remove(id),
    onSuccess: () => {
      message.success('Документ удалён');
      onChanged?.();
    },
    onError: (err: Error) => message.error(err.message),
  });

  const confirmDelete = (record: Document) => {
    Modal.confirm({
      title: 'Удалить документ?',
      content: 'Документ и все связанные файлы будут удалены безвозвратно.',
      okText: 'Удалить',
      okButtonProps: { danger: true },
      cancelText: 'Отмена',
      onOk: () => remove.mutateAsync(record.id),
    });
  };

  if (!loading && documents.length === 0) {
    return (
      <div className="document-list__empty">
        <Empty description="Документов пока нет" />
      </div>
    );
  }

  return (
    <>
      <Spin spinning={loading}>
        <div className="document-list">
          {documents.map((record) => (
            <Card key={record.id} className="document-list__card" bordered={false}>
              <div className="document-list__header">
                <Typography.Paragraph
                  className="document-list__title"
                  ellipsis={{ rows: 2, tooltip: record.title }}
                >
                  {record.title}
                </Typography.Paragraph>
                <div className="document-list__meta">
                  <DocumentStatusBadge status={record.status} />
                  {record.status === 'processing' && record.processing_stage ? (
                    <Tag>{processingStageLabel(record.processing_stage)}</Tag>
                  ) : null}
                </div>
              </div>

              <div className="document-list__footer">
                <Typography.Text className="document-list__info">
                  Обновлён: {formatDate(record.updated_at)}
                  {record.revisions_count > 0
                    ? ` · ${record.revisions_count} ${
                        record.revisions_count === 1 ? 'версия' : 'версий'
                      }`
                    : ''}
                </Typography.Text>
                <DocumentActions
                  record={record}
                  onOpenRevisions={setRevisionsDoc}
                  onReprocess={(id) => reprocess.mutate(id)}
                  onDelete={confirmDelete}
                  reprocessPending={reprocess.isPending}
                />
              </div>
            </Card>
          ))}
        </div>
      </Spin>

      <DocumentRevisionsPanel
        documentId={revisionsDoc?.id ?? ''}
        documentTitle={revisionsDoc?.title ?? ''}
        open={Boolean(revisionsDoc)}
        onClose={() => setRevisionsDoc(null)}
        onRestored={() => {
          onChanged?.();
          setRevisionsDoc(null);
        }}
      />
    </>
  );
}

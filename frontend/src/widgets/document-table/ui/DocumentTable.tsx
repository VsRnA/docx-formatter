import { Button, Space, Table, Tag } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { Link } from 'react-router-dom';
import { useMutation } from '@tanstack/react-query';
import { message } from 'antd';
import type { Document } from '@/entities/document';
import { documentApi } from '@/entities/document';
import { formatDate } from '@/shared/lib/formatDate';
import { ROUTES } from '@/shared/config/env';
import { processingStageLabel } from '@/shared/constants';
import { DocumentStatusBadge } from '@/shared/ui/DocumentStatusBadge';

interface Props {
  documents: Document[];
  loading?: boolean;
  onChanged?: () => void;
}

export function DocumentTable({ documents, loading, onChanged }: Props) {
  const reprocess = useMutation({
    mutationFn: (id: string) => documentApi.reprocess(id),
    onSuccess: () => {
      message.success('Обработка перезапущена');
      onChanged?.();
    },
    onError: (err: Error) => message.error(err.message),
  });

  const columns: ColumnsType<Document> = [
    {
      title: 'Название',
      dataIndex: 'title',
      key: 'title',
      ellipsis: true,
    },
    {
      title: 'Статус',
      dataIndex: 'status',
      key: 'status',
      render: (status: string) => <DocumentStatusBadge status={status} />,
    },
    {
      title: 'Этап',
      dataIndex: 'processing_stage',
      key: 'processing_stage',
      render: (stage: string | null, record) =>
        record.status === 'processing' && stage ? (
          <Tag>{processingStageLabel(stage)}</Tag>
        ) : (
          '—'
        ),
    },
    {
      title: 'Обновлён',
      dataIndex: 'updated_at',
      key: 'updated_at',
      render: (v: string) => formatDate(v),
    },
    {
      title: 'Действия',
      key: 'actions',
      render: (_, record) => (
        <Space wrap>
          {(record.status === 'ready' || record.status === 'draft' || record.status === 'published') && (
            <Link to={ROUTES.documentEdit(record.id)}>
              <Button type="link">Редактировать</Button>
            </Link>
          )}
          {(record.status === 'ready' || record.status === 'draft' || record.status === 'published') && (
            <Link to={ROUTES.documentPreview(record.id)}>
              <Button type="link">Просмотр</Button>
            </Link>
          )}
          {record.slug && (
            <a href={`/p/${record.slug}`} target="_blank" rel="noreferrer">
              <Button type="link">Публикация</Button>
            </a>
          )}
          {(record.status === 'processing' || record.status === 'failed') && (
            <Button
              type="link"
              loading={reprocess.isPending}
              onClick={() => reprocess.mutate(record.id)}
            >
              Перезапустить
            </Button>
          )}
        </Space>
      ),
    },
  ];

  return (
    <Table
      rowKey="id"
      loading={loading}
      columns={columns}
      dataSource={documents}
      pagination={false}
    />
  );
}

import { Card, Typography } from 'antd';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { documentApi } from '@/entities/document';
import { UploadDocument } from '@/features/upload-document';
import { DocumentTable } from '@/widgets/document-table';
import { AppShell } from '@/shared/ui/AppShell';

export function DocumentsListPage() {
  const queryClient = useQueryClient();
  const { data, isLoading } = useQuery({
    queryKey: ['documents'],
    queryFn: () => documentApi.list().then((r) => r.data.data ?? []),
    refetchInterval: (query) => {
      const docs = query.state.data ?? [];
      return docs.some((d) => d.status === 'processing') ? 3000 : false;
    },
  });

  return (
    <AppShell>
      <Typography.Title level={2} style={{ marginTop: 0 }}>
        Инструкции
      </Typography.Title>
      <Typography.Paragraph type="secondary">
        Загрузите .docx от завода — сервис извлечёт структуру, переведёт текст и подготовит HTML для
        доработки и публикации.
      </Typography.Paragraph>
      <Card className="upload-card" style={{ marginBottom: 24 }}>
        <UploadDocument />
      </Card>
      <Card title="Все документы">
        <DocumentTable
          documents={data ?? []}
          loading={isLoading}
          onChanged={() => queryClient.invalidateQueries({ queryKey: ['documents'] })}
        />
      </Card>
    </AppShell>
  );
}

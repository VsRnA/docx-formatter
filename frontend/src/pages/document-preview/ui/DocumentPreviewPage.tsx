import { Link, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Alert, Button, Space, Spin } from 'antd';
import { documentApi } from '@/entities/document';
import { mergeBlocksToEditorHtml } from '@/features/edit-document-flow/lib/blockEditorHtml';
import { PrintDocumentButton } from '@/features/print-document';
import { AppShell } from '@/shared/ui/AppShell';
import { DocumentPageView } from '@/shared/ui/DocumentPageView';
import { ROUTES } from '@/shared/config/env';
import { sortBlocks } from '@/shared/lib/sortBlocks';
import './DocumentPreviewPage.css';

export function DocumentPreviewPage() {
  const { id = '' } = useParams();

  const { data, isLoading, error } = useQuery({
    queryKey: ['document-preview', id],
    queryFn: () => documentApi.editor(id).then((r) => r.data),
    enabled: Boolean(id),
  });

  if (isLoading) {
    return (
      <AppShell compact>
        <Spin style={{ margin: 48, display: 'block' }} />
      </AppShell>
    );
  }

  if (error || !data?.document) {
    return (
      <AppShell compact>
        <Alert type="error" message="Не удалось загрузить документ" style={{ margin: 24 }} />
      </AppShell>
    );
  }

  const html = mergeBlocksToEditorHtml(sortBlocks(data.blocks));

  return (
    <AppShell compact>
      <div className="document-preview-toolbar">
        <Space>
          <Link to={ROUTES.documentEdit(id)}>
            <Button size="small">Редактировать</Button>
          </Link>
          <PrintDocumentButton size="small" />
        </Space>
      </div>
      <DocumentPageView
        html={html}
        layout={data.document.layout}
        className="document-preview-page"
      />
    </AppShell>
  );
}

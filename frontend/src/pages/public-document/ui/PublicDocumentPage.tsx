import { useParams, Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Alert, Spin, Space } from 'antd';
import { publicDocumentApi } from '@/entities/document';
import { PrintDocumentButton } from '@/features/print-document';
import { DocumentPageView } from '@/shared/ui/DocumentPageView';
import { ROUTES } from '@/shared/config/env';
import './PublicDocumentPage.css';

export function PublicDocumentPage() {
  const { slug = '' } = useParams();

  const { data, isLoading, error } = useQuery({
    queryKey: ['public-document', slug],
    queryFn: () => publicDocumentApi.get(slug).then((r) => r.data.data),
    enabled: Boolean(slug),
  });

  if (isLoading) {
    return (
      <div className="public-document-page public-document-page--centered">
        <Spin size="large" />
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="public-document-page public-document-page--centered">
        <Alert
          type="error"
          message="Документ не найден"
          description="Публикация недоступна или ещё не опубликована."
          showIcon
        />
        <Link to={ROUTES.home} className="public-document-page__back">
          К списку документов
        </Link>
      </div>
    );
  }

  return (
    <div className="public-document-page">
      <header className="public-document-page__header">
        <div className="public-document-page__brand">ДРОВОСЕК · Техническая инструкция</div>
        <Space style={{ width: '100%', justifyContent: 'space-between' }} align="start">
          <h1 className="public-document-page__title">{data.title}</h1>
          <PrintDocumentButton size="small" />
        </Space>
      </header>
      <DocumentPageView html={data.html} className="public-document-page__document" />
    </div>
  );
}

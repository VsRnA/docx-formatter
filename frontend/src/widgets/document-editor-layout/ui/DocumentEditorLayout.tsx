import { Button, Layout, Typography } from 'antd';
import { ArrowLeftOutlined } from '@ant-design/icons';
import { Link } from 'react-router-dom';
import type { ReactNode } from 'react';
import './DocumentEditorLayout.css';

const { Header, Content } = Layout;

interface Props {
  title: string;
  backTo?: string;
  status?: ReactNode;
  toolbar: ReactNode;
  formatBar?: ReactNode;
  document: ReactNode;
}

export function DocumentEditorLayout({
  title,
  backTo,
  status,
  toolbar,
  formatBar,
  document,
}: Props) {
  return (
    <Layout className="document-editor-layout document-editor-layout--flow">
      <Header className="document-editor-layout__header">
        <div className="document-editor-layout__title-wrap">
          {backTo ? (
            <Link to={backTo} className="document-editor-layout__back" aria-label="Назад к списку">
              <Button type="text" size="small" icon={<ArrowLeftOutlined />} />
            </Link>
          ) : null}
          <Typography.Title level={5} className="document-editor-layout__title" title={title}>
            {title}
          </Typography.Title>
          {status}
        </div>
        <div className="document-editor-layout__toolbar">{toolbar}</div>
      </Header>
      {formatBar ? <div className="document-editor-layout__ribbon">{formatBar}</div> : null}
      <Content className="document-editor-layout__scroll document-editor-layout__content--flow">
        <div className="document-editor-layout__split">
          <div className="document-editor-layout__editor-pane">{document}</div>
        </div>
      </Content>
    </Layout>
  );
}

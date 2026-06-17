import { Layout, Typography } from 'antd';
import { Link } from 'react-router-dom';
import type { ReactNode } from 'react';
import { ROUTES } from '@/shared/config/env';
import './AppShell.css';

const { Header, Content, Footer } = Layout;

interface Props {
  children: ReactNode;
  /** Hide default page title area */
  compact?: boolean;
}

export function AppShell({ children, compact }: Props) {
  return (
    <Layout className={compact ? 'app-shell app-shell--compact' : 'app-shell'}>
      {!compact ? (
        <Header className="app-shell__header">
          <Link to={ROUTES.home} className="app-shell__brand">
            <span className="app-shell__logo" aria-hidden>
              🪓
            </span>
            <span className="app-shell__brand-text">
              <strong>ДРОВОСЕК</strong>
              <small>Редактор инструкций</small>
            </span>
          </Link>
        </Header>
      ) : null}
      <Content className={compact ? 'app-shell__content app-shell__content--compact' : 'app-shell__content'}>
        {children}
      </Content>
      {!compact ? (
        <Footer className="app-shell__footer">
          <Typography.Text type="secondary">
            Внутренний сервис подготовки технических инструкций · ТСК «Дровосек»
          </Typography.Text>
        </Footer>
      ) : null}
    </Layout>
  );
}

import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { AppProviders } from './providers/AppProviders';
import { AppRouter } from './routes/AppRouter';
import { ErrorBoundary } from '@/shared/ui/ErrorBoundary';
import './styles/global.css';
import '@/shared/styles/document-export.css';

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <ErrorBoundary>
      <AppProviders>
        <AppRouter />
      </AppProviders>
    </ErrorBoundary>
  </StrictMode>,
);

import { BrowserRouter, Route, Routes } from 'react-router-dom';
import { DocumentsListPage } from '@/pages/documents-list';
import { DocumentEditorPage } from '@/pages/document-editor';
import { DocumentPreviewPage } from '@/pages/document-preview';
import { ROUTES } from '@/shared/config/env';

export function AppRouter() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path={ROUTES.home} element={<DocumentsListPage />} />
        <Route path="/documents/:id/edit" element={<DocumentEditorPage />} />
        <Route path="/documents/:id/preview" element={<DocumentPreviewPage />} />
      </Routes>
    </BrowserRouter>
  );
}

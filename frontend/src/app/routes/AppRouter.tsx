import { BrowserRouter, Route, Routes } from 'react-router-dom';
import { DocumentsListPage } from '@/pages/documents-list';
import { DocumentEditorPage } from '@/pages/document-editor';
import { DocumentPreviewPage } from '@/pages/document-preview';
import { PublicDocumentPage } from '@/pages/public-document';
import { ROUTES } from '@/shared/config/env';

export function AppRouter() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path={ROUTES.home} element={<DocumentsListPage />} />
        <Route path="/documents/:id/edit" element={<DocumentEditorPage />} />
        <Route path="/documents/:id/preview" element={<DocumentPreviewPage />} />
        <Route path="/p/:slug" element={<PublicDocumentPage />} />
      </Routes>
    </BrowserRouter>
  );
}

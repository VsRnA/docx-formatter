import { createContext, useContext, useMemo, type ReactNode, type RefObject } from 'react';
import type { Editor } from '@tiptap/react';
import type { DocumentFlowEditorHandle } from '../ui/DocumentFlowEditor';

interface DocumentEditorContextValue {
  editor: Editor | null;
  editorHandleRef: RefObject<DocumentFlowEditorHandle | null>;
}

const DocumentEditorContext = createContext<DocumentEditorContextValue | null>(null);

interface ProviderProps {
  editor: Editor | null;
  editorHandleRef: RefObject<DocumentFlowEditorHandle | null>;
  children: ReactNode;
}

export function DocumentEditorProvider({ editor, editorHandleRef, children }: ProviderProps) {
  const value = useMemo(
    () => ({ editor, editorHandleRef }),
    [editor, editorHandleRef],
  );

  return (
    <DocumentEditorContext.Provider value={value}>
      {children}
    </DocumentEditorContext.Provider>
  );
}

export function useDocumentEditorContext() {
  return useContext(DocumentEditorContext);
}

export function resolveLiveEditor(
  editor: Editor | null,
  editorHandleRef: RefObject<DocumentFlowEditorHandle | null>,
): Editor | null {
  const fromRef = editorHandleRef.current?.getEditor() ?? null;
  const candidate = fromRef ?? editor;

  if (!candidate || candidate.isDestroyed) {
    return null;
  }

  return candidate;
}

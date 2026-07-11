import { useCallback, useEffect, useRef, useState, type RefObject } from 'react';
import { message } from 'antd';
import type { DocumentBlock } from '@/entities/block';
import { documentApi, type SaveDraftBlockPayload } from '@/entities/document';
import type { DocumentFlowEditorHandle } from '@/features/edit-document-flow';

export type AutosaveStatus = 'saved' | 'saving' | 'dirty' | 'error';

interface Options {
  documentId: string;
  blocks: DocumentBlock[];
  editorHandleRef: RefObject<DocumentFlowEditorHandle | null>;
  normalizeEditorHtml: (html: string) => string;
  enabled?: boolean;
  debounceMs?: number;
  flushIntervalMs?: number;
}

export function useAutosaveDraft({
  documentId,
  blocks,
  editorHandleRef,
  normalizeEditorHtml,
  enabled = true,
  debounceMs = 1500,
  flushIntervalMs = 15000,
}: Options) {
  const [status, setStatus] = useState<AutosaveStatus>('saved');
  const [lastSavedAt, setLastSavedAt] = useState<Date | null>(null);
  const blocksRef = useRef(blocks);
  const statusRef = useRef(status);
  const debounceTimerRef = useRef<number | null>(null);
  const flushTimerRef = useRef<number | null>(null);
  const saveQueueRef = useRef<Promise<void>>(Promise.resolve());
  const pendingRef = useRef(false);
  const dirtyBlockIdsRef = useRef<Set<string>>(new Set());
  const fullDocumentDirtyRef = useRef(false);

  blocksRef.current = blocks;
  statusRef.current = status;

  const collectPayload = useCallback((): SaveDraftBlockPayload[] => {
    const updates = editorHandleRef.current?.getBlockUpdates(blocksRef.current) ?? [];
    const normalized = updates.map((block) => ({
      ...block,
      html: block.html ? normalizeEditorHtml(block.html) : block.html,
    }));

    if (fullDocumentDirtyRef.current || dirtyBlockIdsRef.current.size === 0) {
      return normalized;
    }

    const dirtyIds = dirtyBlockIdsRef.current;

    return normalized.filter((block) => dirtyIds.has(block.id));
  }, [editorHandleRef, normalizeEditorHtml]);

  const runSave = useCallback(
    (createAutosaveCheckpoint = false) => {
      saveQueueRef.current = saveQueueRef.current
        .catch(() => undefined)
        .then(async () => {
          const payload = collectPayload();
          if (payload.length === 0) {
            return;
          }

          setStatus('saving');
          try {
            await documentApi.saveDraft(documentId, payload, createAutosaveCheckpoint);
            pendingRef.current = false;
            dirtyBlockIdsRef.current.clear();
            fullDocumentDirtyRef.current = false;
            setStatus('saved');
            setLastSavedAt(new Date());
          } catch {
            pendingRef.current = true;
            setStatus('error');
            message.error('Не удалось автоматически сохранить черновик');
          }
        });

      return saveQueueRef.current;
    },
    [collectPayload, documentId],
  );

  const scheduleSave = useCallback(
    (createAutosaveCheckpoint = false) => {
      pendingRef.current = true;
      if (statusRef.current !== 'saving') {
        setStatus('dirty');
      }

      if (debounceTimerRef.current !== null) {
        window.clearTimeout(debounceTimerRef.current);
      }

      debounceTimerRef.current = window.setTimeout(() => {
        void runSave(createAutosaveCheckpoint);
      }, debounceMs);
    },
    [debounceMs, runSave],
  );

  const flushNow = useCallback(
    (createAutosaveCheckpoint = false) => {
      if (debounceTimerRef.current !== null) {
        window.clearTimeout(debounceTimerRef.current);
        debounceTimerRef.current = null;
      }

      return runSave(createAutosaveCheckpoint);
    },
    [runSave],
  );

  const markDirty = useCallback((blockId?: string) => {
    if (blockId) {
      dirtyBlockIdsRef.current.add(blockId);
    } else {
      fullDocumentDirtyRef.current = true;
    }
    scheduleSave(false);
  }, [scheduleSave]);

  const markFullDocumentDirty = useCallback(() => {
    fullDocumentDirtyRef.current = true;
    scheduleSave(false);
  }, [scheduleSave]);

  useEffect(() => {
    if (!enabled) {
      return;
    }

    flushTimerRef.current = window.setInterval(() => {
      if (pendingRef.current) {
        void flushNow(true);
      }
    }, flushIntervalMs);

    const handleBeforeUnload = (event: BeforeUnloadEvent) => {
      if (!pendingRef.current) {
        return;
      }

      void flushNow(false);
      event.preventDefault();
      event.returnValue = '';
    };

    window.addEventListener('beforeunload', handleBeforeUnload);

    return () => {
      if (debounceTimerRef.current !== null) {
        window.clearTimeout(debounceTimerRef.current);
      }
      if (flushTimerRef.current !== null) {
        window.clearInterval(flushTimerRef.current);
      }
      window.removeEventListener('beforeunload', handleBeforeUnload);
    };
  }, [enabled, flushIntervalMs, flushNow]);

  return {
    status,
    lastSavedAt,
    markDirty,
    markFullDocumentDirty,
    flushNow,
  };
}

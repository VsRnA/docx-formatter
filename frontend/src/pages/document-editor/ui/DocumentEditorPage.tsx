import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Button, Alert, Space, message, Modal } from 'antd';
import { useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import type { Editor } from '@tiptap/react';
import { documentApi } from '@/entities/document';
import type { DocumentBlock } from '@/entities/block';
import { blockApi } from '@/entities/block';
import type { DocumentResource } from '@/entities/resource';
import { DocumentProcessingScreen, useProcessingPoll } from '@/features/poll-processing-status';
import { useSaveDraft, useAutosaveDraft } from '@/features/save-document-draft';
import { DocumentSaveStatus } from '@/features/save-document-draft';
import { DocumentRevisionsPanel } from '@/features/document-revisions';
import { ExportHtmlButton } from '@/features/export-document-html';
import { PrintDocumentButton } from '@/features/print-document';
import { useReprocessDocument } from '@/features/reprocess-document';
import { DocumentOutlineSidebar } from '@/features/document-outline';
import { DownloadTranslatedDocxButton } from '@/features/download-translated-docx';
import {
  buildImageBlockFromResource,
  listUnplacedImageResources,
  UnplacedImagesSidebar,
} from '@/features/unplaced-images';
import {
  DocumentFlowEditor,
  DocumentFormatToolbar,
  DocumentEditorProvider,
  defaultBlockHtml,
  defaultTableHtml,
  normalizeEditorHtml,
  InsertTableDialog,
  BlockActionsToolbar,
  type DocumentFlowEditorHandle,
} from '@/features/edit-document-flow';
import { ImageBlockPanel } from '@/features/manage-block-images';
import { resourceApi } from '@/entities/resource';
import { getImageUploadError } from '@/shared/lib/imageUploadValidation';
import { DocumentEditorLayout } from '@/widgets/document-editor-layout';
import { AppShell } from '@/shared/ui/AppShell';
import { ROUTES } from '@/shared/config/env';
import { DocumentStatusBadge } from '@/shared/ui/DocumentStatusBadge';
import { sortBlocks } from '@/entities/block';

export function DocumentEditorPage() {
  const { id = '' } = useParams();
  const queryClient = useQueryClient();
  const editorHandleRef = useRef<DocumentFlowEditorHandle>(null);
  const [editorInstance, setEditorInstance] = useState<Editor | null>(null);
  const [editorRoot, setEditorRoot] = useState<HTMLElement | null>(null);
  const [blocks, setBlocks] = useState<DocumentBlock[]>([]);
  const [resources, setResources] = useState<DocumentResource[]>([]);
  const [activeBlock, setActiveBlock] = useState<{ id: string; type: string } | null>(null);
  const [imageBlockId, setImageBlockId] = useState<string | null>(null);
  const [outlineRefreshKey, setOutlineRefreshKey] = useState(0);
  const [insertTableOpen, setInsertTableOpen] = useState(false);
  const [revisionsOpen, setRevisionsOpen] = useState(false);
  const [editorReloadToken, setEditorReloadToken] = useState(0);
  const initialEditorLoadRef = useRef(true);

  const statusQuery = useProcessingPoll(id);
  const reprocess = useReprocessDocument(id);
  const editorQuery = useQuery({
    queryKey: ['document-editor', id],
    queryFn: () => documentApi.editor(id).then((r) => r.data),
    enabled:
      Boolean(id) &&
      ['ready', 'draft', 'published'].includes(statusQuery.data?.status ?? ''),
    staleTime: Infinity,
    refetchOnMount: false,
    refetchOnWindowFocus: false,
  });

  const saveDraft = useSaveDraft(id);
  const autosave = useAutosaveDraft({
    documentId: id,
    blocks,
    editorHandleRef,
    normalizeEditorHtml,
    enabled: blocks.length > 0,
  });

  useEffect(() => {
    setBlocks([]);
    setResources([]);
    setImageBlockId(null);
    setActiveBlock(null);
    setEditorReloadToken(0);
    initialEditorLoadRef.current = true;
  }, [id]);

  useEffect(() => {
    if (!editorQuery.data?.blocks) {
      return;
    }

    setBlocks(sortBlocks(editorQuery.data.blocks));
    setResources(editorQuery.data.resources ?? []);

    if (initialEditorLoadRef.current) {
      initialEditorLoadRef.current = false;
      setEditorReloadToken((value) => value + 1);
    }
  }, [editorQuery.dataUpdatedAt]);

  const imageBlockBase = blocks.find((b) => b.id === imageBlockId) ?? null;
  const imageBlock = imageBlockBase
    ? {
        ...imageBlockBase,
        html:
          editorHandleRef.current?.getBlockHtml(imageBlockBase.id, blocks)
          ?? imageBlockBase.html,
      }
    : null;
  const documentKey = `${id}-${editorReloadToken}`;
  const unplacedResources = useMemo(
    () => listUnplacedImageResources(resources, blocks),
    [resources, blocks],
  );

  const handleEditorReady = useCallback((editor: HTMLElement) => {
    setEditorRoot(editor);
  }, []);

  const getBlockHtml = useCallback(
    (blockId: string) =>
      editorHandleRef.current?.getBlockHtml(blockId, blocks)
      ?? blocks.find((block) => block.id === blockId)?.html
      ?? null,
    [blocks],
  );

  const handleBlockHtmlChange = useCallback((blockId: string, html: string) => {
    editorHandleRef.current?.updateBlockHtml(blockId, html);
    setBlocks((prev) =>
      prev.map((block) => (block.id === blockId ? { ...block, html } : block)),
    );
  }, []);

  const handleSave = () => {
    const updates = editorHandleRef.current?.getBlockUpdates(blocks) ?? [];
    const normalized = updates.map((block) => ({
      ...block,
      html: block.html ? normalizeEditorHtml(block.html) : block.html,
    }));

    saveDraft.mutate(normalized, {
      onSuccess: () => {
        setBlocks((prev) => {
          const byId = new Map(normalized.map((block) => [block.id, block]));

          return sortBlocks(
            prev.map((block) => {
              const update = byId.get(block.id);
              if (!update) {
                return block;
              }

              return {
                ...block,
                html: update.html ?? block.html,
                sort: update.sort ?? block.sort,
              };
            }),
          );
        });
      },
    });
  };

  const insertBlock = async (
    type: DocumentBlock['type'],
    html: string,
    assets?: Record<string, unknown> | null,
  ): Promise<DocumentBlock> => {
    await autosave.flushNow();

    const afterBlockId = editorHandleRef.current?.getSelectedBlockId() ?? activeBlock?.id ?? null;
    const sort = afterBlockId
      ? (blocks.find((block) => block.id === afterBlockId)?.sort ?? blocks.length - 1) + 1
      : blocks.length;

    const { data } = await blockApi.create(id, {
      type,
      sort,
      html,
      assets_json: assets ?? null,
    });

    const block: DocumentBlock = {
      ...data.data,
      assets_json: data.data.assets_json ?? assets ?? null,
    };
    const nextBlocks = (() => {
      const next = [...blocks];
      const insertIndex = afterBlockId
        ? next.findIndex((item) => item.id === afterBlockId) + 1
        : next.length;
      next.splice(insertIndex, 0, block);
      return next.map((item, index) => ({ ...item, sort: index }));
    })();

    setBlocks(nextBlocks);
    editorHandleRef.current?.insertBlockAfter(afterBlockId, block, nextBlocks);
    setActiveBlock({ id: block.id, type: block.type });
    editorHandleRef.current?.scrollToBlock(block.id);
    setOutlineRefreshKey((value) => value + 1);
    autosave.markFullDocumentDirty();

    return block;
  };

  const handlePasteOrDropImage = useCallback(
    async (file: File) => {
      const validationError = getImageUploadError(file);
      if (validationError) {
        message.error(validationError);
        return;
      }

      try {
        const { data } = await resourceApi.uploadImage(id, file);
        const resource = data.data;
        const payload = buildImageBlockFromResource(resource);
        await insertBlock('image', payload.html, payload.assets);
        message.success('Изображение вставлено');
      } catch (error) {
        message.error(error instanceof Error ? error.message : 'Не удалось загрузить изображение');
      }
    },
    [id],
  );

  const handleAddImage = async () => {
    const block = await insertBlock('image', defaultBlockHtml('image'));
    setImageBlockId(block.id);
    message.success('Изображение добавлено');
  };

  const handleInsertHeading2 = () => void insertBlock('heading', defaultBlockHtml('heading2'));
  const handleInsertHeading3 = () => void insertBlock('heading', defaultBlockHtml('heading3'));
  const handleInsertParagraph = () => void insertBlock('paragraph', defaultBlockHtml('paragraph'));
  const handleInsertTable = (options?: { rows: number; cols: number; withHeaderRow: boolean }) => {
    const html = defaultTableHtml(
      options?.rows ?? 3,
      options?.cols ?? 3,
      options?.withHeaderRow ?? true,
    );
    void insertBlock('table', html);
  };

  const reorderLocalBlocks = (orderedIds: string[]) => {
    setBlocks((prev) => {
      const byId = new Map(prev.map((block) => [block.id, block]));
      return sortBlocks(
        orderedIds
          .map((blockId, index) => {
            const block = byId.get(blockId);
            return block ? { ...block, sort: index } : null;
          })
          .filter((block): block is DocumentBlock => block !== null),
      );
    });
    editorHandleRef.current?.reorderBlocks(orderedIds);
  };

  const handleMoveBlock = async (blockId: string, direction: -1 | 1) => {
    const index = blocks.findIndex((block) => block.id === blockId);
    const targetIndex = index + direction;
    if (index < 0 || targetIndex < 0 || targetIndex >= blocks.length) {
      return;
    }

    await autosave.flushNow();

    const orderedIds = blocks.map((block) => block.id);
    [orderedIds[index], orderedIds[targetIndex]] = [orderedIds[targetIndex], orderedIds[index]];
    reorderLocalBlocks(orderedIds);
    await blockApi.reorder(id, orderedIds);
  };

  const handleDuplicateBlock = async (blockId: string) => {
    await autosave.flushNow();

    const { data } = await blockApi.duplicate(id, blockId);
    const block = data.data;
    const nextBlocks = sortBlocks([...blocks, block]);
    setBlocks(nextBlocks);
    editorHandleRef.current?.insertBlockAfter(blockId, block, nextBlocks);
    setActiveBlock({ id: block.id, type: block.type });
  };

  const handleDeleteBlock = async (blockId: string) => {
    await autosave.flushNow();

    await blockApi.remove(id, blockId);
    editorHandleRef.current?.removeBlock(blockId);
    setBlocks((prev) =>
      prev.filter((block) => block.id !== blockId).map((block, index) => ({ ...block, sort: index })),
    );
    setActiveBlock(null);
  };

  const handleTogglePageBreak = (blockId: string, enabled: boolean) => {
    editorHandleRef.current?.setBlockPageBreakBefore(blockId, enabled);
    setBlocks((prev) =>
      prev.map((block) => {
        if (block.id !== blockId) {
          return block;
        }

        const meta = { ...(block.meta_json ?? {}) };
        if (enabled) {
          meta.page_break_before = true;
        } else {
          delete meta.page_break_before;
        }

        return {
          ...block,
          meta_json: Object.keys(meta).length > 0 ? meta : null,
        };
      }),
    );
    autosave.markDirty(blockId);
  };

  const handleInsertUnplacedImage = async (resource: DocumentResource) => {
    try {
      const payload = buildImageBlockFromResource(resource);
      await insertBlock('image', payload.html, payload.assets);
      message.success('Изображение вставлено');
    } catch (error) {
      message.error(error instanceof Error ? error.message : 'Не удалось вставить изображение');
    }
  };

  const handleReprocess = () => {
    reprocess.mutate(undefined, {
      onSuccess: async () => {
        await autosave.flushNow();
        queryClient.invalidateQueries({ queryKey: ['document-status', id] });
        await queryClient.invalidateQueries({ queryKey: ['document-editor', id] });
        setEditorReloadToken((value) => value + 1);
      },
    });
  };

  const statusTag = statusQuery.data ? (
    <DocumentStatusBadge status={statusQuery.data.status} className="document-editor-status" />
  ) : null;

  const processingScreen = (
    <DocumentProcessingScreen
      stage={statusQuery.data?.processing_stage}
      parseCoverage={statusQuery.data?.parse_coverage}
      onReprocess={
        statusQuery.data?.status === 'processing' || statusQuery.data?.status === 'failed'
          ? handleReprocess
          : undefined
      }
      reprocessing={reprocess.isPending}
    />
  );

  if (statusQuery.isLoading) {
    return (
      <AppShell compact>
        {processingScreen}
      </AppShell>
    );
  }

  if (statusQuery.data?.status === 'processing' || statusQuery.data?.status === 'uploading') {
    return (
      <AppShell compact>
        {processingScreen}
      </AppShell>
    );
  }

  if (statusQuery.data?.status === 'failed') {
    return (
      <AppShell compact>
        <Alert
          type="error"
          message="Ошибка обработки"
          description={statusQuery.data.processing_error}
          style={{ margin: 24 }}
          action={
            <Button size="small" onClick={handleReprocess} loading={reprocess.isPending}>
              Перезапустить
            </Button>
          }
        />
      </AppShell>
    );
  }

  if (editorQuery.isLoading || blocks.length === 0) {
    return (
      <AppShell compact>
        {processingScreen}
      </AppShell>
    );
  }

  const activeBlockEntity = blocks.find((block) => block.id === activeBlock?.id) ?? null;

  const doc = editorQuery.data?.document;

  return (
    <AppShell compact>
      <DocumentEditorProvider editor={editorInstance} editorHandleRef={editorHandleRef}>
      <DocumentEditorLayout
        title={doc?.title ?? 'Редактор'}
        backTo={ROUTES.home}
        status={statusTag}
        formatBar={
          <>
            <BlockActionsToolbar
              activeBlock={activeBlockEntity}
              blocks={blocks}
              onMoveUp={(blockId) => void handleMoveBlock(blockId, -1)}
              onMoveDown={(blockId) => void handleMoveBlock(blockId, 1)}
              onDuplicate={(blockId) => void handleDuplicateBlock(blockId)}
              onDelete={(blockId) => void handleDeleteBlock(blockId)}
              onTogglePageBreak={handleTogglePageBreak}
            />
            <DocumentFormatToolbar
              documentId={id}
              activeBlock={activeBlock}
              getBlockHtml={getBlockHtml}
              onBlockHtmlChange={(blockId, html) => {
                handleBlockHtmlChange(blockId, html);
                autosave.markDirty(blockId);
              }}
              onInsertImage={() => void handleAddImage()}
              onInsertHeading2={() => void handleInsertHeading2()}
              onInsertHeading3={() => void handleInsertHeading3()}
              onInsertParagraph={() => void handleInsertParagraph()}
              onOpenInsertTable={() => setInsertTableOpen(true)}
            />
          </>
        }
        toolbar={
          <Space size={8} wrap>
            <DocumentSaveStatus status={autosave.status} />
            <Button size="small" onClick={handleSave} loading={saveDraft.isPending}>
              Сохранить сейчас
            </Button>
            <Button size="small" onClick={() => setRevisionsOpen(true)}>
              История версий
            </Button>
            <DownloadTranslatedDocxButton
              documentId={id}
              available={Boolean(statusQuery.data?.has_translated_docx)}
              size="small"
            />
            <ExportHtmlButton documentId={id} size="small" />
            <PrintDocumentButton size="small" />
          </Space>
        }
        leftSidebar={
          <DocumentOutlineSidebar
            editorRoot={editorRoot}
            refreshKey={outlineRefreshKey}
            onNavigate={(blockId) => editorHandleRef.current?.scrollToBlock(blockId)}
          />
        }
        rightSidebar={
          <UnplacedImagesSidebar
            resources={unplacedResources}
            onInsert={(resource) => void handleInsertUnplacedImage(resource)}
          />
        }
        document={
          <DocumentFlowEditor
            ref={editorHandleRef}
            blocks={blocks}
            documentKey={documentKey}
            activeBlockId={activeBlock?.id ?? null}
            layout={doc?.layout}
            onEditorReady={handleEditorReady}
            onEditorInstanceReady={setEditorInstance}
            onActiveBlockChange={(blockId, blockType) => {
              setActiveBlock(blockId ? { id: blockId, type: blockType ?? 'paragraph' } : null);
            }}
            onImageBlockClick={setImageBlockId}
            onTextInput={() => {
              setOutlineRefreshKey((value) => value + 1);
              if (activeBlock?.id) {
                autosave.markDirty(activeBlock.id);
              }
            }}
            onPasteImage={(file) => void handlePasteOrDropImage(file)}
            onDropImage={(file) => void handlePasteOrDropImage(file)}
          />
        }
      />

      </DocumentEditorProvider>
      <Modal
        title="Изображение"
        open={imageBlock !== null}
        onCancel={() => setImageBlockId(null)}
        footer={null}
        width={480}
        destroyOnClose
      >
        {imageBlock ? (
          <ImageBlockPanel
            documentId={id}
            block={imageBlock}
            onBlockUpdate={(blockId, html, assets) => {
              editorHandleRef.current?.updateBlockHtml(blockId, html);
              setBlocks((prev) =>
                prev.map((block) =>
                  block.id === blockId ? { ...block, html, assets_json: assets ?? block.assets_json } : block,
                ),
              );
              autosave.markDirty(blockId);
            }}
          />
        ) : null}
      </Modal>
      <InsertTableDialog
        open={insertTableOpen}
        onCancel={() => setInsertTableOpen(false)}
        onInsert={(options) => {
          setInsertTableOpen(false);
          handleInsertTable(options);
        }}
      />
      <DocumentRevisionsPanel
        documentId={id}
        documentTitle={doc?.title ?? ''}
        open={revisionsOpen}
        onClose={() => setRevisionsOpen(false)}
        onRestored={async () => {
          await autosave.flushNow();
          await queryClient.invalidateQueries({ queryKey: ['document-editor', id] });
          setEditorReloadToken((value) => value + 1);
        }}
      />
    </AppShell>
  );
}

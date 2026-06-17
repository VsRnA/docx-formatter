import { useCallback, useEffect, useRef, useState } from 'react';
import { Button, Alert, Space, message, Modal } from 'antd';
import { PictureOutlined } from '@ant-design/icons';
import { Link, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { documentApi } from '@/entities/document';
import type { DocumentBlock } from '@/entities/block';
import { blockApi } from '@/entities/block';
import { DocumentProcessingScreen, useProcessingPoll } from '@/features/poll-processing-status';
import { useSaveDraft } from '@/features/save-document-draft';
import { PublishButton } from '@/features/publish-document';
import { ExportHtmlButton } from '@/features/export-document-html';
import { PrintDocumentButton } from '@/features/print-document';
import { DownloadTranslatedDocxButton } from '@/features/download-translated-docx';
import { ParseWarningsButton } from '@/features/review-parse-warnings';
import { TranslationReviewButton } from '@/features/review-translation';
import { useReprocessDocument } from '@/features/reprocess-document';
import {
  DocumentFlowEditor,
  DocumentFormatToolbar,
  defaultBlockHtml,
  normalizeEditorHtml,
  type DocumentFlowEditorHandle,
} from '@/features/edit-document-flow';
import { ImageBlockPanel } from '@/features/manage-block-images';
import { DocumentEditorLayout } from '@/widgets/document-editor-layout';
import { AppShell } from '@/shared/ui/AppShell';
import { ROUTES } from '@/shared/config/env';
import { DocumentStatusBadge } from '@/shared/ui/DocumentStatusBadge';
import { sortBlocks } from '@/shared/lib/sortBlocks';

export function DocumentEditorPage() {
  const { id = '' } = useParams();
  const queryClient = useQueryClient();
  const editorHandleRef = useRef<DocumentFlowEditorHandle>(null);
  const editorRootRef = useRef<HTMLDivElement | null>(null);
  const [blocks, setBlocks] = useState<DocumentBlock[]>([]);
  const [imageBlockId, setImageBlockId] = useState<string | null>(null);

  const statusQuery = useProcessingPoll(id);
  const reprocess = useReprocessDocument(id);
  const editorQuery = useQuery({
    queryKey: ['document-editor', id],
    queryFn: () => documentApi.editor(id).then((r) => r.data),
    enabled:
      Boolean(id) &&
      ['ready', 'draft', 'published'].includes(statusQuery.data?.status ?? ''),
  });

  const saveDraft = useSaveDraft(id);

  useEffect(() => {
    setBlocks([]);
    setImageBlockId(null);
  }, [id]);

  useEffect(() => {
    if (editorQuery.data?.blocks) {
      setBlocks(sortBlocks(editorQuery.data.blocks));
    }
  }, [editorQuery.data]);

  const imageBlock = blocks.find((b) => b.id === imageBlockId) ?? null;
  const documentKey = `${id}-${editorQuery.dataUpdatedAt}`;

  const handleEditorReady = useCallback((editor: HTMLDivElement) => {
    editorRootRef.current = editor;
  }, []);

  const handleSave = () => {
    const updates = editorHandleRef.current?.getBlockUpdates(blocks) ?? [];
    saveDraft.mutate(
      updates.map((block) => ({
        ...block,
        html: block.html ? normalizeEditorHtml(block.html) : block.html,
      })),
    );
  };

  const handleAddImage = async () => {
    const sort = blocks.length;
    const { data } = await blockApi.create(id, {
      type: 'image',
      sort,
      html: defaultBlockHtml('image'),
    });

    const block = data.data;
    setBlocks((prev) => [...sortBlocks(prev), block]);
    editorHandleRef.current?.appendBlock(block);
    setImageBlockId(block.id);
    editorHandleRef.current?.scrollToBlock(block.id);
    message.success('Изображение добавлено');
  };

  const handleReprocess = () => {
    reprocess.mutate(undefined, {
      onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['document-status', id] });
        queryClient.invalidateQueries({ queryKey: ['document-editor', id] });
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

  const doc = editorQuery.data?.document;

  return (
    <AppShell compact>
      <DocumentEditorLayout
        title={doc?.title ?? 'Редактор'}
        backTo={ROUTES.home}
        status={statusTag}
        formatBar={
          <DocumentFormatToolbar
            editorRef={editorRootRef}
            onInsertImage={() => void handleAddImage()}
          />
        }
        toolbar={
          <Space size={8} wrap>
            <Button size="small" onClick={handleSave} loading={saveDraft.isPending}>
              Сохранить
            </Button>
            <Button size="small" icon={<PictureOutlined />} onClick={() => void handleAddImage()}>
              Изображение
            </Button>
            <Link to={ROUTES.documentPreview(id)}>
              <Button size="small">Просмотр</Button>
            </Link>
            <TranslationReviewButton blocks={blocks} size="small" />
            <ParseWarningsButton warnings={statusQuery.data?.parse_warnings} size="small" />
            <PublishButton documentId={id} size="small" />
            <ExportHtmlButton documentId={id} size="small" />
            <DownloadTranslatedDocxButton
              documentId={id}
              available={statusQuery.data?.has_translated_docx}
              size="small"
            />
            <PrintDocumentButton size="small" />
          </Space>
        }
        document={
          <DocumentFlowEditor
            ref={editorHandleRef}
            blocks={blocks}
            documentKey={documentKey}
            layout={doc?.layout}
            onEditorReady={handleEditorReady}
            onImageBlockClick={setImageBlockId}
          />
        }
      />

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
            }}
          />
        ) : null}
      </Modal>
    </AppShell>
  );
}

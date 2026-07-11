import {
  forwardRef,
  useEffect,
  useImperativeHandle,
  useMemo,
  useRef,
} from 'react';
import { EditorContent, useEditor, type Editor } from '@tiptap/react';
import type { DocumentBlock } from '@/entities/block';
import type { SaveDraftBlockPayload } from '@/entities/document';
import { documentLayoutStyleVars, type DocumentLayout } from '@/shared/lib/documentLayoutStyle';
import { repairBlockWrapperHtml } from '../lib/blockEditorHtml';
import {
  blocksToEditorJson,
  createTableDocBlockReplacement,
  extractBlockUpdatesFromEditor,
  findBlockDomPos,
  getActiveBlockFromEditor,
} from '../lib/tiptap/blockConversion';
import {
  insertBlockAfterNode,
  insertBlockNodeAt,
  resyncEditorBlocks,
} from '../lib/tiptap/insertBlockNode';
import { getDocumentEditorExtensions } from '../lib/tiptap/getExtensions';
import { TableBubbleMenu } from './TableBubbleMenu';
import { TableGutterOverlay } from './TableGutterOverlay';
import './DocumentFlowEditor.css';

export interface DocumentFlowEditorHandle {
  getBlockUpdates: (blocks: DocumentBlock[]) => SaveDraftBlockPayload[];
  getRoot: () => HTMLElement | null;
  getEditor: () => Editor | null;
  appendBlock: (block: DocumentBlock, allBlocks?: DocumentBlock[]) => void;
  insertBlockAfter: (afterBlockId: string | null, block: DocumentBlock, allBlocks?: DocumentBlock[]) => void;
  removeBlock: (blockId: string) => void;
  reorderBlocks: (orderedIds: string[]) => void;
  updateBlockHtml: (blockId: string, html: string) => void;
  scrollToBlock: (blockId: string) => void;
  getSelectedBlockId: () => string | null;
  getBlockHtml: (blockId: string, sourceBlocks: DocumentBlock[]) => string | null;
  setBlockPageBreakBefore: (blockId: string, enabled: boolean) => void;
}

interface Props {
  blocks: DocumentBlock[];
  documentKey: string;
  layout?: DocumentLayout | null;
  activeBlockId?: string | null;
  onEditorReady?: (editor: HTMLElement) => void;
  onEditorInstanceReady?: (editor: Editor | null) => void;
  onActiveBlockChange?: (blockId: string | null, blockType: string | null) => void;
  onImageBlockClick?: (blockId: string) => void;
  onTextInput?: () => void;
  onUndo?: () => boolean;
  onRedo?: () => boolean;
  onPasteImage?: (file: File) => void;
  onDropImage?: (file: File) => void;
}

export const DocumentFlowEditor = forwardRef<DocumentFlowEditorHandle, Props>(
  function DocumentFlowEditor(
    {
      blocks,
      documentKey,
      layout,
      activeBlockId,
      onEditorReady,
      onEditorInstanceReady,
      onActiveBlockChange,
      onImageBlockClick,
      onTextInput,
      onPasteImage,
      onDropImage,
    },
    ref,
  ) {
    const loadedSnapshotRef = useRef<{ editor: Editor; documentKey: string } | null>(null);
    const activeBlockIdRef = useRef<string | null>(null);
    const blocksRef = useRef(blocks);
    const pendingInsertsRef = useRef<Array<{ afterBlockId: string | null; block: DocumentBlock }>>([]);
    blocksRef.current = blocks;
    const layoutStyle = documentLayoutStyleVars(layout);
    const extensions = useMemo(
      () =>
        getDocumentEditorExtensions({
          onEditImage: (blockId: string) => onImageBlockClick?.(blockId),
        }),
      [onImageBlockClick],
    );

    const editor = useEditor(
      {
      extensions,
      immediatelyRender: false,
      editorProps: {
        attributes: {
          class: 'document-flow-editor__content document-root',
        },
        handleDOMEvents: {
          click: (_view, event) => {
            const target = event.target;
            if (!(target instanceof Element)) {
              return false;
            }

            const wrapper = target.closest<HTMLElement>('[data-block-id]');
            const blockId = wrapper?.dataset.blockId ?? null;
            const blockType = wrapper?.dataset.blockType ?? null;

            if (blockId) {
              activeBlockIdRef.current = blockId;
              onActiveBlockChange?.(blockId, blockType);
            }

            const editTrigger = target.closest('[data-image-edit-trigger]');
            if (editTrigger && wrapper?.dataset.blockType === 'image' && blockId) {
              onImageBlockClick?.(blockId);
              return true;
            }

            return false;
          },
        },
        handlePaste: (_view, event) => {
          if (!onPasteImage) {
            return false;
          }

          const items = event.clipboardData?.items;
          if (!items) {
            return false;
          }

          for (const item of Array.from(items)) {
            if (!item.type.startsWith('image/')) {
              continue;
            }

            const file = item.getAsFile();
            if (!file) {
              continue;
            }

            event.preventDefault();
            onPasteImage(file);
            return true;
          }

          return false;
        },
      },
      onUpdate: () => {
        onTextInput?.();
      },
      onSelectionUpdate: ({ editor: currentEditor }) => {
        const active = getActiveBlockFromEditor(currentEditor);
        if (!active) {
          return;
        }

        activeBlockIdRef.current = active.id;
        onActiveBlockChange?.(active.id, active.type);
      },
    },
    [extensions],
    );

    useEffect(() => {
      onEditorInstanceReady?.(editor ?? null);

      return () => {
        onEditorInstanceReady?.(null);
      };
    }, [editor, onEditorInstanceReady]);

    useImperativeHandle(ref, () => ({
      getBlockUpdates: (sourceBlocks) =>
        editor ? extractBlockUpdatesFromEditor(editor, sourceBlocks) : [],
      getRoot: () => editor?.view.dom ?? null,
      getEditor: () => editor,
      appendBlock: (block, allBlocks) => {
        if (!editor) {
          pendingInsertsRef.current.push({ afterBlockId: null, block });
          return;
        }

        const blocksSnapshot = allBlocks ?? blocksRef.current;
        if (block.type === 'image') {
          resyncEditorBlocks(editor, blocksSnapshot);
          return;
        }

        const inserted = insertBlockNodeAt(editor, editor.state.doc.content.size, block);
        if (!inserted) {
          resyncEditorBlocks(editor, blocksSnapshot);
        }
      },
      insertBlockAfter: (afterBlockId, block, allBlocks) => {
        if (!editor) {
          pendingInsertsRef.current.push({ afterBlockId, block });
          return;
        }

        const blocksSnapshot = allBlocks ?? blocksRef.current;
        insertBlockAfterNode(editor, afterBlockId, block, blocksSnapshot);
      },
      removeBlock: (blockId) => {
        if (!editor) return;

        const pos = findBlockDomPos(editor, blockId);
        if (pos === null) return;

        const node = editor.state.doc.nodeAt(pos);
        if (!node) return;

        editor.chain().focus().deleteRange({ from: pos, to: pos + node.nodeSize }).run();
      },
      reorderBlocks: (orderedIds) => {
        if (!editor) return;

        const current = extractBlockUpdatesFromEditor(editor, blocks);
        const byId = new Map(current.map((item) => [item.id, item]));
        const reordered = orderedIds
          .map((id) => byId.get(id))
          .filter((item): item is SaveDraftBlockPayload => Boolean(item));

        const sourceById = new Map(blocks.map((block) => [block.id, block]));
        const nextBlocks = reordered.map((item, index) => {
          const source = sourceById.get(item.id);
          return source ? { ...source, sort: index, html: item.html } : null;
        }).filter((block): block is DocumentBlock => block !== null);

        editor.commands.setContent(blocksToEditorJson(nextBlocks));
      },
      updateBlockHtml: (blockId, html) => {
        if (!editor) return;

        const pos = findBlockDomPos(editor, blockId);
        if (pos === null) return;

        const node = editor.state.doc.nodeAt(pos);
        if (!node) return;

        if (node.type.name === 'imageDocBlock') {
          editor.view.dispatch(
            editor.state.tr.setNodeMarkup(pos, undefined, {
              ...node.attrs,
              html: repairBlockWrapperHtml(html),
            }),
          );
          return;
        }

        if (node.type.name === 'tableDocBlock') {
          const replacement = createTableDocBlockReplacement(
            blockId,
            (node.attrs.blockType as string) ?? 'table',
            Boolean(node.attrs.pageBreakBefore),
            html,
          );
          editor.view.dispatch(
            editor.state.tr.replaceWith(
              pos,
              pos + node.nodeSize,
              editor.schema.nodeFromJSON(replacement),
            ),
          );
          return;
        }

        const repaired = repairBlockWrapperHtml(html);
        const replacement = blocksToEditorJson([
          {
            id: blockId,
            document_id: '',
            type: ((node.attrs.blockType as DocumentBlock['type']) ?? 'paragraph'),
            sort: 0,
            html: repaired,
            content_json: null,
            text_original: null,
            text_translated: null,
            translation_status: 'skipped',
            styles_json: null,
            meta_json: null,
            assets_json: null,
          },
        ]).content?.[0];

        if (replacement) {
          editor.view.dispatch(
            editor.state.tr.replaceWith(
              pos,
              pos + node.nodeSize,
              editor.schema.nodeFromJSON(replacement),
            ),
          );
        }
      },
      scrollToBlock: (blockId) => {
        if (!editor) return;

        const pos = findBlockDomPos(editor, blockId);
        if (pos === null) return;

        const dom = editor.view.nodeDOM(pos);
        if (dom instanceof HTMLElement) {
          dom.scrollIntoView({ behavior: 'smooth', block: 'center' });
          dom.classList.add('doc-flow-block--highlight');
          window.setTimeout(() => dom.classList.remove('doc-flow-block--highlight'), 1200);
        }
      },
      getSelectedBlockId: () => {
        if (editor) {
          const active = getActiveBlockFromEditor(editor);
          if (active) {
            return active.id;
          }
        }

        return activeBlockIdRef.current;
      },
      getBlockHtml: (blockId, sourceBlocks) => {
        if (!editor) {
          return sourceBlocks.find((block) => block.id === blockId)?.html ?? null;
        }

        const pos = findBlockDomPos(editor, blockId);
        if (pos === null) {
          return sourceBlocks.find((block) => block.id === blockId)?.html ?? null;
        }

        const node = editor.state.doc.nodeAt(pos);
        if (!node) {
          return sourceBlocks.find((block) => block.id === blockId)?.html ?? null;
        }

        if (node.type.name === 'imageDocBlock') {
          return repairBlockWrapperHtml((node.attrs.html as string) ?? '');
        }

        if (node.type.name === 'tableDocBlock') {
          return sourceBlocks.find((block) => block.id === blockId)?.html ?? null;
        }

        return sourceBlocks.find((block) => block.id === blockId)?.html ?? null;
      },
      setBlockPageBreakBefore: (blockId, enabled) => {
        if (!editor) return;

        const pos = findBlockDomPos(editor, blockId);
        if (pos === null) return;

        const node = editor.state.doc.nodeAt(pos);
        if (!node) return;

        editor.view.dispatch(
          editor.state.tr.setNodeMarkup(pos, undefined, {
            ...node.attrs,
            pageBreakBefore: enabled,
          }),
        );
      },
    }));

    useEffect(() => {
      if (!editor || pendingInsertsRef.current.length === 0) {
        return;
      }

      const pending = [...pendingInsertsRef.current];
      pendingInsertsRef.current = [];

      pending.forEach(({ afterBlockId, block }) => {
        insertBlockAfterNode(editor, afterBlockId, block, blocksRef.current);
      });
    }, [editor, blocks]);

    useEffect(() => {
      if (!editor || blocks.length === 0) {
        return;
      }

      const snapshot = loadedSnapshotRef.current;
      if (snapshot?.editor === editor && snapshot.documentKey === documentKey) {
        return;
      }

      editor.commands.setContent(blocksToEditorJson(blocks), { emitUpdate: false });
      loadedSnapshotRef.current = { editor, documentKey };
      onEditorReady?.(editor.view.dom);
    }, [blocks, documentKey, editor, onEditorReady]);

    useEffect(() => {
      if (!editor) return;

      editor.view.dom.querySelectorAll<HTMLElement>('.doc-flow-block').forEach((wrapper) => {
        wrapper.classList.toggle(
          'doc-flow-block--active',
          wrapper.dataset.blockId === activeBlockId,
        );
      });
    }, [activeBlockId, editor, blocks]);

    if (!editor) {
      return (
        <div className="document-flow-editor-wrap document-flow-editor-wrap--loading">
          <div className="document-flow-editor">
            <div className="document-flow-editor__canvas" style={layoutStyle}>
              <div className="document-flow-editor__page-frame" style={layoutStyle}>
                <p className="document-flow-editor__loading">Загрузка редактора…</p>
              </div>
            </div>
          </div>
        </div>
      );
    }

    return (
      <div
        className="document-flow-editor-wrap"
        onDragOver={(event) => {
          if (!onDropImage) {
            return;
          }

          if (Array.from(event.dataTransfer.types).includes('Files')) {
            event.preventDefault();
          }
        }}
        onDrop={(event) => {
          if (!onDropImage) {
            return;
          }

          const file = Array.from(event.dataTransfer.files).find((item) => item.type.startsWith('image/'));
          if (!file) {
            return;
          }

          event.preventDefault();
          onDropImage(file);
        }}
      >
        <div className="document-flow-editor">
          <div className="document-flow-editor__canvas" style={layoutStyle}>
            <div className="document-flow-editor__page-frame" style={layoutStyle}>
              <EditorContent editor={editor} style={layoutStyle} />
              <TableBubbleMenu editor={editor} />
              <TableGutterOverlay editor={editor} />
            </div>
          </div>
        </div>
      </div>
    );
  },
);

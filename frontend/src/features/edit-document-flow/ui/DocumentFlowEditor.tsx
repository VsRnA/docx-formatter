import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react';
import type { DocumentBlock } from '@/entities/block';
import type { SaveDraftBlockPayload } from '@/entities/document';
import { documentLayoutStyleVars, type DocumentLayout } from '@/shared/lib/documentLayoutStyle';
import {
  mergeBlocksToEditorHtml,
  extractBlockUpdates,
  insertBlockAfter,
  removeBlockFromEditor,
  reorderBlocksInEditor,
  scrollToBlock,
  setBlockPageBreakBefore,
} from '../lib/blockEditorHtml';
import './DocumentFlowEditor.css';

export interface DocumentFlowEditorHandle {
  getBlockUpdates: (blocks: DocumentBlock[]) => SaveDraftBlockPayload[];
  getRoot: () => HTMLDivElement | null;
  appendBlock: (block: DocumentBlock) => void;
  insertBlockAfter: (afterBlockId: string | null, block: DocumentBlock) => void;
  removeBlock: (blockId: string) => void;
  reorderBlocks: (orderedIds: string[]) => void;
  updateBlockHtml: (blockId: string, html: string) => void;
  scrollToBlock: (blockId: string) => void;
  getSelectedBlockId: () => string | null;
  setBlockPageBreakBefore: (blockId: string, enabled: boolean) => void;
}

interface Props {
  blocks: DocumentBlock[];
  documentKey: string;
  layout?: DocumentLayout | null;
  activeBlockId?: string | null;
  onEditorReady?: (editor: HTMLDivElement) => void;
  onActiveBlockChange?: (blockId: string | null, blockType: string | null) => void;
  onImageBlockClick?: (blockId: string) => void;
  onTextInput?: () => void;
  onUndo?: () => boolean;
  onRedo?: () => boolean;
  onPasteImage?: (file: File) => void;
}

function syncActiveBlock(editor: HTMLElement, activeBlockId: string | null | undefined): void {
  editor.querySelectorAll<HTMLElement>('.doc-flow-block').forEach((wrapper) => {
    wrapper.classList.toggle('doc-flow-block--active', wrapper.dataset.blockId === activeBlockId);
  });
}

function protectImageBlocks(container: HTMLElement): void {
  container.querySelectorAll<HTMLElement>('[data-block-type="image"]').forEach((wrapper) => {
    wrapper.querySelectorAll('figure, img, .doc-image__placeholder').forEach((element) => {
      element.setAttribute('contenteditable', 'false');
    });
  });

  container.querySelectorAll<HTMLElement>('figure.doc-image, .doc-image--page-decoration').forEach(
    (element) => {
      element.setAttribute('contenteditable', 'false');
      element.querySelectorAll('img').forEach((child) => {
        child.setAttribute('contenteditable', 'false');
      });
    },
  );

  container.querySelectorAll<HTMLElement>('.doc-anchored-canvas img, .doc-page-overlay figure').forEach(
    (element) => {
      element.setAttribute('contenteditable', 'false');
    },
  );

  container.querySelectorAll<HTMLElement>('.doc-figure-gallery, .doc-figure-canvas').forEach((gallery) => {
    gallery.setAttribute('contenteditable', 'false');
    gallery.querySelectorAll('figure, figcaption, img, svg, .doc-figure-overlay, .doc-figure-gallery__canvas, .doc-figure-gallery__captions, .doc-figure-canvas__layer, .doc-figure-canvas__captions').forEach((element) => {
      element.setAttribute('contenteditable', 'false');
    });
  });
}

function getBlockIdFromNode(node: Node | null, editor: HTMLElement): string | null {
  if (!node) return null;
  const element = node instanceof Element ? node : node.parentElement;
  const wrapper = element?.closest<HTMLElement>('[data-block-id]');
  if (!wrapper || !editor.contains(wrapper)) return null;
  return wrapper.dataset.blockId ?? null;
}

export const DocumentFlowEditor = forwardRef<DocumentFlowEditorHandle, Props>(
  function DocumentFlowEditor(
    {
      blocks,
      documentKey,
      layout,
      activeBlockId,
      onEditorReady,
      onActiveBlockChange,
      onImageBlockClick,
      onTextInput,
      onUndo,
      onRedo,
      onPasteImage,
    },
    ref,
  ) {
    const editorRef = useRef<HTMLDivElement>(null);
    const loadedKeyRef = useRef<string | null>(null);
    const activeBlockIdRef = useRef<string | null>(null);
    const layoutStyle = documentLayoutStyleVars(layout);

    useImperativeHandle(ref, () => ({
      getBlockUpdates: (sourceBlocks) =>
        editorRef.current
          ? extractBlockUpdates(editorRef.current, sourceBlocks)
          : sourceBlocks.map((block) => ({
              id: block.id,
              type: block.type,
              sort: block.sort,
              html: block.html,
              styles: block.styles_json,
              meta: block.meta_json,
            })),
      getRoot: () => editorRef.current,
      appendBlock: (block) => {
        if (!editorRef.current) return;
        insertBlockAfter(editorRef.current, null, block);
        protectImageBlocks(editorRef.current);
      },
      insertBlockAfter: (afterBlockId, block) => {
        if (!editorRef.current) return;
        insertBlockAfter(editorRef.current, afterBlockId, block);
        protectImageBlocks(editorRef.current);
      },
      removeBlock: (blockId) => {
        if (!editorRef.current) return;
        removeBlockFromEditor(editorRef.current, blockId);
      },
      reorderBlocks: (orderedIds) => {
        if (!editorRef.current) return;
        reorderBlocksInEditor(editorRef.current, orderedIds);
      },
      updateBlockHtml: (blockId, html) => {
        const wrapper = editorRef.current?.querySelector<HTMLElement>(`[data-block-id="${blockId}"]`);
        if (wrapper) {
          wrapper.innerHTML = html;
          if (editorRef.current) {
            protectImageBlocks(editorRef.current);
          }
        }
      },
      scrollToBlock: (blockId) => {
        if (!editorRef.current) return;
        scrollToBlock(editorRef.current, blockId);
      },
      getSelectedBlockId: () => {
        if (!editorRef.current) return activeBlockIdRef.current;
        const selection = window.getSelection();
        const fromSelection = getBlockIdFromNode(selection?.anchorNode ?? null, editorRef.current);
        return fromSelection ?? activeBlockIdRef.current;
      },
      setBlockPageBreakBefore: (blockId, enabled) => {
        if (!editorRef.current) return;
        setBlockPageBreakBefore(editorRef.current, blockId, enabled);
      },
    }));

    useEffect(() => {
      loadedKeyRef.current = null;
    }, [documentKey]);

    useEffect(() => {
      if (!editorRef.current || blocks.length === 0) {
        return;
      }

      if (loadedKeyRef.current === documentKey) {
        return;
      }

      editorRef.current.innerHTML = mergeBlocksToEditorHtml(blocks);
      protectImageBlocks(editorRef.current);
      loadedKeyRef.current = documentKey;
      onEditorReady?.(editorRef.current);
    }, [blocks, documentKey, onEditorReady]);

    useEffect(() => {
      const editor = editorRef.current;
      if (!editor) return;

      const rememberActiveBlock = () => {
        const blockId = getBlockIdFromNode(window.getSelection()?.anchorNode ?? null, editor);
        if (!blockId) return;

        activeBlockIdRef.current = blockId;
        const wrapper = editor.querySelector<HTMLElement>(`[data-block-id="${blockId}"]`);
        onActiveBlockChange?.(blockId, wrapper?.dataset.blockType ?? null);
        syncActiveBlock(editor, blockId);
      };

      const handleKeyDown = (event: KeyboardEvent) => {
        if (event.ctrlKey || event.metaKey) {
          const key = event.key.toLowerCase();

          if (key === 'z' && !event.shiftKey) {
            if (onUndo?.()) {
              event.preventDefault();
              return;
            }
            event.preventDefault();
            document.execCommand('undo');
            return;
          }

          if ((key === 'z' && event.shiftKey) || key === 'y') {
            if (onRedo?.()) {
              event.preventDefault();
              return;
            }
            event.preventDefault();
            document.execCommand('redo');
            return;
          }

          if (key === 'b') {
            event.preventDefault();
            document.execCommand('bold');
            return;
          }

          if (key === 'i') {
            event.preventDefault();
            document.execCommand('italic');
            return;
          }

          if (key === 'u') {
            event.preventDefault();
            document.execCommand('underline');
          }
        }
      };

      const handlePaste = (event: ClipboardEvent) => {
        if (!onPasteImage) {
          return;
        }

        const items = event.clipboardData?.items;
        if (!items) {
          return;
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
          return;
        }
      };

      const handleInput = () => {
        onTextInput?.();
      };

      editor.addEventListener('keyup', rememberActiveBlock);
      editor.addEventListener('mouseup', rememberActiveBlock);
      editor.addEventListener('focusin', rememberActiveBlock);
      editor.addEventListener('keydown', handleKeyDown);
      editor.addEventListener('paste', handlePaste);
      editor.addEventListener('input', handleInput);

      return () => {
        editor.removeEventListener('keyup', rememberActiveBlock);
        editor.removeEventListener('mouseup', rememberActiveBlock);
        editor.removeEventListener('focusin', rememberActiveBlock);
        editor.removeEventListener('keydown', handleKeyDown);
        editor.removeEventListener('paste', handlePaste);
        editor.removeEventListener('input', handleInput);
      };
    }, [onActiveBlockChange, onPasteImage, onRedo, onTextInput, onUndo]);

    useEffect(() => {
      if (!editorRef.current) return;
      syncActiveBlock(editorRef.current, activeBlockId);
    }, [activeBlockId, blocks]);

    const handleClick = (event: React.MouseEvent<HTMLDivElement>) => {
      const target = event.target;
      if (!(target instanceof Element) || !editorRef.current) return;

      const wrapper = target.closest<HTMLElement>('[data-block-id]');
      const blockId = wrapper?.dataset.blockId ?? null;
      const blockType = wrapper?.dataset.blockType ?? null;

      if (blockId) {
        activeBlockIdRef.current = blockId;
        onActiveBlockChange?.(blockId, blockType);
        syncActiveBlock(editorRef.current, blockId);
      }

      if (wrapper?.dataset.blockType === 'image') {
        onImageBlockClick?.(wrapper.dataset.blockId ?? '');
      }
    };

    return (
      <div className="document-flow-editor-wrap">
        <div className="document-flow-editor">
          <div className="document-flow-editor__canvas" style={layoutStyle}>
            <div className="document-flow-editor__page-frame" style={layoutStyle}>
              <div
                ref={editorRef}
                className="document-flow-editor__content document-root"
                style={layoutStyle}
                contentEditable
                suppressContentEditableWarning
                spellCheck={false}
                onClick={handleClick}
              />
            </div>
          </div>
        </div>
      </div>
    );
  },
);

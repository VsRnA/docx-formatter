import { useCallback, useRef } from 'react';
import { EditOutlined } from '@ant-design/icons';
import { NodeViewWrapper, type NodeViewProps } from '@tiptap/react';
import { repairBlockWrapperHtml } from '../blockEditorHtml';

function readNaturalWidth(html: string): number | null {
  if (typeof document === 'undefined') {
    return null;
  }

  const container = document.createElement('div');
  container.innerHTML = html;
  const figure = container.querySelector<HTMLElement>('figure.doc-image');
  if (!figure) {
    return null;
  }

  const fromData = figure.getAttribute('data-ooxml-width');
  if (fromData && /^\d+$/.test(fromData)) {
    return Number(fromData);
  }

  const styleWidth = figure.style.width?.replace('px', '');
  if (styleWidth && /^\d+$/.test(styleWidth)) {
    return Number(styleWidth);
  }

  const img = figure.querySelector<HTMLImageElement>('img');
  if (img?.naturalWidth) {
    return img.naturalWidth;
  }

  return null;
}

export function ImageBlockNodeView({ node, selected, updateAttributes, extension }: NodeViewProps) {
  const html = repairBlockWrapperHtml(node.attrs.html ?? '');
  const resizeRef = useRef<{ startX: number; startWidth: number } | null>(null);
  const displayWidth = node.attrs.displayWidth as number | null;
  const naturalWidth = displayWidth ?? readNaturalWidth(html);
  const hasImage = html.includes('<img');
  const blockId = typeof node.attrs.blockId === 'string' ? node.attrs.blockId : null;

  const openEditor = useCallback(
    (event: React.MouseEvent) => {
      event.preventDefault();
      event.stopPropagation();

      if (!blockId) {
        return;
      }

      extension.options.onEditImage?.(blockId);
    },
    [blockId, extension.options],
  );

  const handlePointerDown = useCallback(
    (event: React.PointerEvent<HTMLSpanElement>) => {
      event.preventDefault();
      event.stopPropagation();

      const figure = (event.currentTarget.closest('.doc-flow-block') as HTMLElement | null)
        ?.querySelector<HTMLElement>('figure.doc-image');
      const currentWidth = figure?.getBoundingClientRect().width ?? naturalWidth ?? 320;

      resizeRef.current = { startX: event.clientX, startWidth: currentWidth };

      const handleMove = (moveEvent: PointerEvent) => {
        if (!resizeRef.current) {
          return;
        }

        const delta = moveEvent.clientX - resizeRef.current.startX;
        const nextWidth = Math.max(80, Math.round(resizeRef.current.startWidth + delta));
        updateAttributes({ displayWidth: nextWidth });

        if (typeof document === 'undefined') {
          return;
        }

        const container = document.createElement('div');
        container.innerHTML = html;
        const targetFigure = container.querySelector<HTMLElement>('figure.doc-image');
        const img = targetFigure?.querySelector<HTMLImageElement>('img');
        if (targetFigure) {
          targetFigure.style.width = `${nextWidth}px`;
          targetFigure.style.maxWidth = `${nextWidth}px`;
          targetFigure.dataset.ooxmlWidth = String(nextWidth);
          if (img) {
            img.style.width = `${nextWidth}px`;
            img.style.maxWidth = `${nextWidth}px`;
            img.style.height = 'auto';
          }
        }

        updateAttributes({ html: container.innerHTML, displayWidth: nextWidth });
      };

      const handleUp = () => {
        resizeRef.current = null;
        window.removeEventListener('pointermove', handleMove);
        window.removeEventListener('pointerup', handleUp);
      };

      window.addEventListener('pointermove', handleMove);
      window.addEventListener('pointerup', handleUp);
    },
    [html, naturalWidth, updateAttributes],
  );

  const blockStyle =
    naturalWidth && !html.includes('doc-image--flowing')
      ? ({ '--doc-image-display-width': `${displayWidth ?? naturalWidth}px` } as React.CSSProperties)
      : undefined;

  return (
    <NodeViewWrapper
      as="div"
      className={`doc-block doc-flow-block${selected ? ' doc-flow-block--active' : ''}`}
      data-block-id={node.attrs.blockId}
      data-block-type="image"
      data-drag-handle=""
      style={blockStyle}
      contentEditable={false}
      onDoubleClick={hasImage ? openEditor : undefined}
    >
      <div
        className="doc-flow-image-block"
        dangerouslySetInnerHTML={{ __html: html }}
        style={
          displayWidth
            ? {
                maxWidth: displayWidth,
              }
            : naturalWidth
              ? { maxWidth: naturalWidth }
              : undefined
        }
      />
      {hasImage ? (
        <>
          <button
            type="button"
            className="doc-flow-image-block__edit"
            data-image-edit-trigger=""
            aria-label="Редактировать изображение"
            onMouseDown={(event) => event.stopPropagation()}
            onClick={openEditor}
          >
            <EditOutlined />
          </button>
          <span
            className="doc-flow-image-block__resize"
            role="presentation"
            onPointerDown={handlePointerDown}
          />
        </>
      ) : null}
    </NodeViewWrapper>
  );
}

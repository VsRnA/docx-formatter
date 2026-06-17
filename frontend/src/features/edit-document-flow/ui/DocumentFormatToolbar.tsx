import type { ReactNode, RefObject } from 'react';
import { Button, Select, Tooltip } from 'antd';
import {
  AlignCenterOutlined,
  AlignLeftOutlined,
  AlignRightOutlined,
  BoldOutlined,
  ItalicOutlined,
  OrderedListOutlined,
  InsertRowAboveOutlined,
  PictureOutlined,
  RedoOutlined,
  UnderlineOutlined,
  UndoOutlined,
  UnorderedListOutlined,
} from '@ant-design/icons';
import {
  applyBlockStyle,
  applyFontFamily,
  applyFontSize,
  BLOCK_STYLES,
  execEditorCommand,
  FONT_FAMILIES,
  FONT_SIZES,
} from '../lib/editorCommands';
import { applyImageAlign, parseImageBlock, type ImageAlign } from '@/shared/lib/imageBlockHtml';
import { useFormatToolbarState } from '../hooks/useFormatToolbarState';
import './DocumentFormatToolbar.css';

interface ActiveBlock {
  id: string;
  type: string;
}

interface Props {
  editorRef: RefObject<HTMLDivElement | null>;
  activeBlock?: ActiveBlock | null;
  getBlockHtml?: (blockId: string) => string | null | undefined;
  onBlockHtmlChange?: (blockId: string, html: string) => void;
  onInsertImage?: () => void;
  onUndo?: () => boolean;
  onRedo?: () => boolean;
  pageBreakBefore?: boolean;
  onTogglePageBreakBefore?: () => void;
}

function preventBlur(event: React.MouseEvent) {
  event.preventDefault();
}

function ToolbarGroup({ children }: { children: ReactNode }) {
  return <div className="document-format-toolbar__group">{children}</div>;
}

export function DocumentFormatToolbar({
  editorRef,
  activeBlock,
  getBlockHtml,
  onBlockHtmlChange,
  onInsertImage,
  onUndo,
  onRedo,
  pageBreakBefore = false,
  onTogglePageBreakBefore,
}: Props) {
  const textState = useFormatToolbarState(editorRef);
  const isImageSelected = activeBlock?.type === 'image';
  const imageHtml = isImageSelected && activeBlock ? getBlockHtml?.(activeBlock.id) : null;
  const imageAlign = isImageSelected ? parseImageBlock(imageHtml ?? null).align : null;

  const run = (command: string, value?: string) => {
    execEditorCommand(editorRef.current, command, value);
  };

  const handleUndo = () => {
    if (onUndo?.()) {
      return;
    }
    run('undo');
  };

  const handleRedo = () => {
    if (onRedo?.()) {
      return;
    }
    run('redo');
  };

  const applyAlign = (align: ImageAlign) => {
    if (!isImageSelected || !activeBlock) {
      const command =
        align === 'left' ? 'justifyLeft' : align === 'center' ? 'justifyCenter' : 'justifyRight';
      run(command);
      return;
    }

    const html = applyImageAlign(getBlockHtml?.(activeBlock.id) ?? null, align);
    onBlockHtmlChange?.(activeBlock.id, html);
  };

  const alignLeftActive = isImageSelected ? imageAlign === 'left' : textState.alignLeft;
  const alignCenterActive = isImageSelected ? imageAlign === 'center' : textState.alignCenter;
  const alignRightActive = isImageSelected ? imageAlign === 'right' : textState.alignRight;

  return (
    <div className="document-format-toolbar">
      <div className="document-format-toolbar__groups">
        <ToolbarGroup>
          <Tooltip title="Отменить (Ctrl+Z)">
            <Button
              type="text"
              size="small"
              icon={<UndoOutlined />}
              onMouseDown={preventBlur}
              onClick={handleUndo}
            />
          </Tooltip>
          <Tooltip title="Повторить (Ctrl+Y)">
            <Button
              type="text"
              size="small"
              icon={<RedoOutlined />}
              onMouseDown={preventBlur}
              onClick={handleRedo}
            />
          </Tooltip>
        </ToolbarGroup>

        <ToolbarGroup>
          <Select
            className="document-format-toolbar__style"
            size="small"
            defaultValue="p"
            disabled={isImageSelected}
            popupMatchSelectWidth={false}
            options={BLOCK_STYLES.map((style) => ({ label: style.label, value: style.value }))}
            onMouseDown={preventBlur}
            onChange={(tag) => applyBlockStyle(editorRef.current, tag)}
          />
          <Select
            className="document-format-toolbar__font"
            size="small"
            defaultValue="Times New Roman"
            disabled={isImageSelected}
            popupMatchSelectWidth={false}
            options={FONT_FAMILIES.map((font) => ({ label: font.label, value: font.value }))}
            onMouseDown={preventBlur}
            onChange={(font) => applyFontFamily(editorRef.current, font)}
          />
          <Select
            className="document-format-toolbar__size"
            size="small"
            defaultValue="12pt"
            disabled={isImageSelected}
            popupMatchSelectWidth={false}
            options={FONT_SIZES.map((size) => ({ label: size.label, value: size.value }))}
            onMouseDown={preventBlur}
            onChange={(size) => applyFontSize(editorRef.current, size)}
          />
        </ToolbarGroup>

        <ToolbarGroup>
          <Tooltip title="Жирный (Ctrl+B)">
            <Button
              type={textState.bold ? 'primary' : 'text'}
              size="small"
              icon={<BoldOutlined />}
              disabled={isImageSelected}
              onMouseDown={preventBlur}
              onClick={() => run('bold')}
            />
          </Tooltip>
          <Tooltip title="Курсив (Ctrl+I)">
            <Button
              type={textState.italic ? 'primary' : 'text'}
              size="small"
              icon={<ItalicOutlined />}
              disabled={isImageSelected}
              onMouseDown={preventBlur}
              onClick={() => run('italic')}
            />
          </Tooltip>
          <Tooltip title="Подчёркивание (Ctrl+U)">
            <Button
              type={textState.underline ? 'primary' : 'text'}
              size="small"
              icon={<UnderlineOutlined />}
              disabled={isImageSelected}
              onMouseDown={preventBlur}
              onClick={() => run('underline')}
            />
          </Tooltip>
        </ToolbarGroup>

        {onTogglePageBreakBefore ? (
          <ToolbarGroup>
            <Tooltip title="Начать с новой страницы">
              <Button
                type={pageBreakBefore ? 'primary' : 'text'}
                size="small"
                icon={<InsertRowAboveOutlined />}
                disabled={isImageSelected || !activeBlock}
                onMouseDown={preventBlur}
                onClick={onTogglePageBreakBefore}
              />
            </Tooltip>
          </ToolbarGroup>
        ) : null}

        <ToolbarGroup>
          <Tooltip title="Маркированный список">
            <Button
              type={textState.unorderedList ? 'primary' : 'text'}
              size="small"
              icon={<UnorderedListOutlined />}
              disabled={isImageSelected}
              onMouseDown={preventBlur}
              onClick={() => run('insertUnorderedList')}
            />
          </Tooltip>
          <Tooltip title="Нумерованный список">
            <Button
              type={textState.orderedList ? 'primary' : 'text'}
              size="small"
              icon={<OrderedListOutlined />}
              disabled={isImageSelected}
              onMouseDown={preventBlur}
              onClick={() => run('insertOrderedList')}
            />
          </Tooltip>
        </ToolbarGroup>

        <ToolbarGroup>
          <Tooltip title={isImageSelected ? 'Выровнять изображение по левому краю' : 'По левому краю'}>
            <Button
              type={alignLeftActive ? 'primary' : 'text'}
              size="small"
              icon={<AlignLeftOutlined />}
              onMouseDown={preventBlur}
              onClick={() => applyAlign('left')}
            />
          </Tooltip>
          <Tooltip title={isImageSelected ? 'Выровнять изображение по центру' : 'По центру'}>
            <Button
              type={alignCenterActive ? 'primary' : 'text'}
              size="small"
              icon={<AlignCenterOutlined />}
              onMouseDown={preventBlur}
              onClick={() => applyAlign('center')}
            />
          </Tooltip>
          <Tooltip title={isImageSelected ? 'Выровнять изображение по правому краю' : 'По правому краю'}>
            <Button
              type={alignRightActive ? 'primary' : 'text'}
              size="small"
              icon={<AlignRightOutlined />}
              onMouseDown={preventBlur}
              onClick={() => applyAlign('right')}
            />
          </Tooltip>
        </ToolbarGroup>

        {onInsertImage ? (
          <ToolbarGroup>
            <Tooltip title="Вставить изображение">
              <Button
                type="text"
                size="small"
                icon={<PictureOutlined />}
                onMouseDown={(event) => {
                  preventBlur(event);
                  onInsertImage();
                }}
              />
            </Tooltip>
          </ToolbarGroup>
        ) : null}
      </div>
    </div>
  );
}

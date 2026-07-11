import type { ReactNode } from 'react';
import { Button, Input, Popover, Select, Space, Spin, Tooltip, message } from 'antd';
import {
  AlignCenterOutlined,
  AlignLeftOutlined,
  AlignRightOutlined,
  BoldOutlined,
  ItalicOutlined,
  LinkOutlined,
  OrderedListOutlined,
  PictureOutlined,
  RedoOutlined,
  RobotOutlined,
  StrikethroughOutlined,
  UnderlineOutlined,
  UndoOutlined,
  UnorderedListOutlined,
} from '@ant-design/icons';
import { FONT_FAMILIES, FONT_SIZES, BLOCK_STYLES } from '../lib/formatConstants';
import { applyImageAlign, applyImageWrap, parseImageBlock, type ImageAlign, type ImageWrap } from '@/shared/lib/imageBlockHtml';
import { useFormatToolbarState } from '../hooks/useFormatToolbarState';
import { useMutation } from '@tanstack/react-query';
import { documentAiApi } from '@/entities/document';
import { useState } from 'react';
import {
  resolveLiveEditor,
  useDocumentEditorContext,
} from '../lib/editorContext';
import { keepEditorSelection, runToolbarCommand } from '../lib/toolbarCommand';
import './DocumentFormatToolbar.css';

interface ActiveBlock {
  id: string;
  type: string;
}

interface Props {
  documentId: string;
  activeBlock?: ActiveBlock | null;
  getBlockHtml?: (blockId: string) => string | null | undefined;
  onBlockHtmlChange?: (blockId: string, html: string) => void;
  onInsertImage?: () => void;
  onInsertHeading2?: () => void;
  onInsertHeading3?: () => void;
  onInsertParagraph?: () => void;
  onInsertTable?: () => void;
  onOpenInsertTable?: () => void;
}

function ToolbarGroup({ children }: { children: ReactNode }) {
  return <div className="document-format-toolbar__group">{children}</div>;
}

const AI_PRESETS = [
  { label: 'Сократить', prompt: 'Сократи текст, сохрани смысл.' },
  { label: 'Формальнее', prompt: 'Перепиши текст в более формальном стиле.' },
  { label: 'Грамматика', prompt: 'Исправь грамматику и пунктуацию, не меняя смысл.' },
] as const;

export function DocumentFormatToolbar({
  documentId,
  activeBlock,
  getBlockHtml,
  onBlockHtmlChange,
  onInsertImage,
  onInsertHeading2,
  onInsertHeading3,
  onInsertParagraph,
  onInsertTable,
  onOpenInsertTable,
}: Props) {
  const editorContext = useDocumentEditorContext();
  const editor = editorContext
    ? resolveLiveEditor(editorContext.editor, editorContext.editorHandleRef)
    : null;
  const textState = useFormatToolbarState(editor);
  const isImageSelected = activeBlock?.type === 'image';
  const isTableSelected = activeBlock?.type === 'table';
  const imageHtml = isImageSelected && activeBlock ? getBlockHtml?.(activeBlock.id) : null;
  const imageAlign = isImageSelected ? parseImageBlock(imageHtml ?? null).align : null;
  const imageWrap = isImageSelected ? parseImageBlock(imageHtml ?? null).wrap : null;
  const [aiOpen, setAiOpen] = useState(false);
  const [customPrompt, setCustomPrompt] = useState('');
  const [linkUrl, setLinkUrl] = useState('');

  const selectedText =
    editor && !editor.state.selection.empty
      ? editor.state.doc.textBetween(editor.state.selection.from, editor.state.selection.to, '\n')
      : '';

  const canUseAi = Boolean(editor && selectedText.trim().length > 0 && !isImageSelected);
  const formattingDisabled = isImageSelected || !editor;

  const reworkMutation = useMutation({
    mutationFn: (prompt: string) =>
      documentAiApi
        .rework(documentId, {
          block_id: activeBlock?.id ?? null,
          text: selectedText,
          prompt,
        })
        .then((response) => response.data.text),
    onSuccess: (text) => {
      if (!editor) {
        return;
      }

      const { from, to } = editor.state.selection;
      editor.chain().focus().insertContentAt({ from, to }, text).run();
      setAiOpen(false);
      setCustomPrompt('');
      message.success('Текст переработан');
    },
    onError: (error: Error) => {
      message.error(error.message || 'Не удалось переработать текст');
    },
  });

  const applyBlockStyle = (style: string) => {
    if (!editor) return;

    if (style === 'heading2') {
      editor.chain().focus().setHeading({ level: 2 }).run();
      return;
    }

    if (style === 'heading3') {
      editor.chain().focus().setHeading({ level: 3 }).run();
      return;
    }

    editor.chain().focus().setParagraph().run();
  };

  const applyAlign = (align: ImageAlign) => {
    if (!isImageSelected || !activeBlock) {
      if (!editor) return;

      const command =
        align === 'left' ? 'left' : align === 'center' ? 'center' : 'right';
      editor.chain().focus().setTextAlign(command).run();
      return;
    }

    const html = applyImageAlign(getBlockHtml?.(activeBlock.id) ?? null, align);
    onBlockHtmlChange?.(activeBlock.id, html);
  };

  const alignLeftActive = isImageSelected ? imageAlign === 'left' : textState.alignLeft;
  const alignCenterActive = isImageSelected ? imageAlign === 'center' : textState.alignCenter;
  const alignRightActive = isImageSelected ? imageAlign === 'right' : textState.alignRight;
  const alignJustifyActive = !isImageSelected && textState.alignJustify;

  const applyLink = () => {
    if (!editor) return;
    const href = linkUrl.trim();
    if (!href) {
      editor.chain().focus().unsetLink().run();
      return;
    }

    editor.chain().focus().extendMarkRange('link').setLink({ href }).run();
    setLinkUrl('');
  };

  const aiPopover = (
    <div className="document-format-toolbar__ai-popover">
      <div className="document-format-toolbar__ai-presets">
        {AI_PRESETS.map((preset) => (
          <Button
            key={preset.label}
            size="small"
            loading={reworkMutation.isPending}
            onClick={() => reworkMutation.mutate(preset.prompt)}
          >
            {preset.label}
          </Button>
        ))}
      </div>
      <Input.TextArea
        rows={3}
        value={customPrompt}
        placeholder="Свой промпт для YandexGPT"
        onChange={(event) => setCustomPrompt(event.target.value)}
      />
      <Button
        type="primary"
        block
        disabled={customPrompt.trim() === ''}
        loading={reworkMutation.isPending}
        onClick={() => reworkMutation.mutate(customPrompt.trim())}
      >
        Переработать
      </Button>
    </div>
  );

  return (
    <div className="document-format-toolbar">
      <div className="document-format-toolbar__groups">
        <ToolbarGroup>
          <Tooltip title="Отменить (Ctrl+Z)">
            <Button
              type="text"
              size="small"
              icon={<UndoOutlined />}
              disabled={!editor?.can().undo()}
              onMouseDown={(event) => keepEditorSelection(event)}
              onClick={() => editor?.chain().focus().undo().run()}
            />
          </Tooltip>
          <Tooltip title="Повторить (Ctrl+Y)">
            <Button
              type="text"
              size="small"
              icon={<RedoOutlined />}
              disabled={!editor?.can().redo()}
              onMouseDown={(event) => keepEditorSelection(event)}
              onClick={() => editor?.chain().focus().redo().run()}
            />
          </Tooltip>
        </ToolbarGroup>

        <ToolbarGroup>
          <div onMouseDown={keepEditorSelection}>
            <Select
              className="document-format-toolbar__style"
              size="small"
              value={textState.blockStyle}
              disabled={formattingDisabled || isTableSelected}
              popupMatchSelectWidth={false}
              options={BLOCK_STYLES.map((style) => ({ label: style.label, value: style.value }))}
              onChange={(style) => applyBlockStyle(style)}
            />
          </div>
          <div onMouseDown={keepEditorSelection}>
            <Select
              className="document-format-toolbar__font"
              size="small"
              value={textState.fontFamily}
              disabled={formattingDisabled}
              popupMatchSelectWidth={false}
              options={FONT_FAMILIES.map((font) => ({ label: font.label, value: font.value }))}
              onChange={(font) => editor?.chain().focus().setFontFamily(font).run()}
            />
          </div>
          <div onMouseDown={keepEditorSelection}>
            <Select
              className="document-format-toolbar__size"
              size="small"
              value={textState.fontSize}
              disabled={formattingDisabled}
              popupMatchSelectWidth={false}
              options={FONT_SIZES.map((size) => ({ label: size.label, value: size.value }))}
              onChange={(size) => editor?.chain().focus().setMark('textStyle', { fontSize: size }).run()}
            />
          </div>
        </ToolbarGroup>

        <ToolbarGroup>
          <Tooltip title="Жирный (Ctrl+B)">
            <Button
              type={textState.bold ? 'primary' : 'text'}
              size="small"
              icon={<BoldOutlined />}
              disabled={formattingDisabled}
              onMouseDown={(event) =>
                runToolbarCommand(event, editor, (currentEditor) => {
                  currentEditor.chain().focus().toggleBold().run();
                })
              }
            />
          </Tooltip>
          <Tooltip title="Курсив (Ctrl+I)">
            <Button
              type={textState.italic ? 'primary' : 'text'}
              disabled={formattingDisabled}
              size="small"
              icon={<ItalicOutlined />}
              onMouseDown={(event) =>
                runToolbarCommand(event, editor, (currentEditor) => {
                  currentEditor.chain().focus().toggleItalic().run();
                })
              }
            />
          </Tooltip>
          <Tooltip title="Подчёркивание (Ctrl+U)">
            <Button
              type={textState.underline ? 'primary' : 'text'}
              size="small"
              icon={<UnderlineOutlined />}
              disabled={formattingDisabled}
              onMouseDown={(event) =>
                runToolbarCommand(event, editor, (currentEditor) => {
                  currentEditor.chain().focus().toggleUnderline().run();
                })
              }
            />
          </Tooltip>
          <Tooltip title="Зачёркивание">
            <Button
              type={textState.strike ? 'primary' : 'text'}
              size="small"
              icon={<StrikethroughOutlined />}
              disabled={formattingDisabled}
              onMouseDown={(event) =>
                runToolbarCommand(event, editor, (currentEditor) => {
                  currentEditor.chain().focus().toggleStrike().run();
                })
              }
            />
          </Tooltip>
        </ToolbarGroup>

        <ToolbarGroup>
          <Popover
            trigger="click"
            content={
              <Input
                size="small"
                placeholder="#000000"
                value={textState.textColor ?? undefined}
                onChange={(event) =>
                  editor?.chain().focus().setColor(event.target.value).run()
                }
              />
            }
          >
            <Button size="small" disabled={formattingDisabled}>
              Цвет
            </Button>
          </Popover>
          <Popover
            trigger="click"
            content={
              <Input
                size="small"
                placeholder="#fff59d"
                value={textState.highlightColor ?? undefined}
                onChange={(event) =>
                  editor?.chain().focus().setHighlight({ color: event.target.value }).run()
                }
              />
            }
          >
            <Button size="small" disabled={formattingDisabled}>
              Выделение
            </Button>
          </Popover>
          <Popover
            trigger="click"
            content={
              <Space direction="vertical" style={{ width: 220 }}>
                <Input
                  size="small"
                  placeholder="https://example.com"
                  value={linkUrl || textState.linkHref || ''}
                  onChange={(event) => setLinkUrl(event.target.value)}
                />
                <Button size="small" type="primary" block onClick={applyLink}>
                  Применить ссылку
                </Button>
              </Space>
            }
          >
            <Button
              type={textState.linkHref ? 'primary' : 'text'}
              size="small"
              icon={<LinkOutlined />}
              disabled={formattingDisabled}
              onMouseDown={keepEditorSelection}
            />
          </Popover>
        </ToolbarGroup>

        {(onInsertHeading2 || onInsertHeading3 || onInsertParagraph || onInsertTable || onOpenInsertTable) ? (
          <ToolbarGroup>
            {onInsertHeading2 ? (
              <Tooltip title="Вставить заголовок 2">
                <Button type="text" size="small" onClick={onInsertHeading2}>
                  H2
                </Button>
              </Tooltip>
            ) : null}
            {onInsertHeading3 ? (
              <Tooltip title="Вставить заголовок 3">
                <Button type="text" size="small" onClick={onInsertHeading3}>
                  H3
                </Button>
              </Tooltip>
            ) : null}
            {onInsertParagraph ? (
              <Tooltip title="Вставить текст">
                <Button type="text" size="small" onClick={onInsertParagraph}>
                  Текст
                </Button>
              </Tooltip>
            ) : null}
            {onOpenInsertTable ? (
              <Tooltip title="Вставить таблицу">
                <Button type="text" size="small" onClick={onOpenInsertTable}>
                  Таблица
                </Button>
              </Tooltip>
            ) : null}
            {onInsertTable ? (
              <Tooltip title="Вставить таблицу">
                <Button type="text" size="small" onClick={onInsertTable}>
                  Таблица
                </Button>
              </Tooltip>
            ) : null}
          </ToolbarGroup>
        ) : null}

        <ToolbarGroup>
          <Tooltip title="Маркированный список">
            <Button
              type={textState.unorderedList ? 'primary' : 'text'}
              size="small"
              icon={<UnorderedListOutlined />}
              disabled={formattingDisabled || isTableSelected}
              onMouseDown={(event) =>
                runToolbarCommand(event, editor, (currentEditor) => {
                  currentEditor.chain().focus().toggleBulletList().run();
                })
              }
            />
          </Tooltip>
          <Tooltip title="Нумерованный список">
            <Button
              type={textState.orderedList ? 'primary' : 'text'}
              size="small"
              icon={<OrderedListOutlined />}
              disabled={formattingDisabled || isTableSelected}
              onMouseDown={(event) =>
                runToolbarCommand(event, editor, (currentEditor) => {
                  currentEditor.chain().focus().toggleOrderedList().run();
                })
              }
            />
          </Tooltip>
        </ToolbarGroup>

        <ToolbarGroup>
          <Tooltip title={isImageSelected ? 'Выровнять изображение по левому краю' : 'По левому краю'}>
            <Button
              type={alignLeftActive ? 'primary' : 'text'}
              size="small"
              icon={<AlignLeftOutlined />}
              disabled={!editor && !isImageSelected}
              onMouseDown={keepEditorSelection}
              onClick={() => applyAlign('left')}
            />
          </Tooltip>
          <Tooltip title={isImageSelected ? 'Выровнять изображение по центру' : 'По центру'}>
            <Button
              type={alignCenterActive ? 'primary' : 'text'}
              size="small"
              icon={<AlignCenterOutlined />}
              disabled={!editor && !isImageSelected}
              onMouseDown={keepEditorSelection}
              onClick={() => applyAlign('center')}
            />
          </Tooltip>
          <Tooltip title={isImageSelected ? 'Выровнять изображение по правому краю' : 'По правому краю'}>
            <Button
              type={alignRightActive ? 'primary' : 'text'}
              size="small"
              icon={<AlignRightOutlined />}
              disabled={!editor && !isImageSelected}
              onMouseDown={keepEditorSelection}
              onClick={() => applyAlign('right')}
            />
          </Tooltip>
          {!isImageSelected ? (
            <Tooltip title="По ширине">
              <Button
                type={alignJustifyActive ? 'primary' : 'text'}
                size="small"
                disabled={!editor}
                onMouseDown={keepEditorSelection}
                onClick={() => editor?.chain().focus().setTextAlign('justify').run()}
              >
                J
              </Button>
            </Tooltip>
          ) : null}
          {isImageSelected && activeBlock ? (
            <Select
              size="small"
              value={imageWrap ?? 'inline'}
              popupMatchSelectWidth={false}
              options={[
                { label: 'В строке', value: 'inline' },
                { label: 'Обтекание', value: 'square' },
                { label: 'Плотное', value: 'tight' },
              ]}
              onChange={(wrap: ImageWrap) => {
                const html = applyImageWrap(getBlockHtml?.(activeBlock.id) ?? null, wrap);
                onBlockHtmlChange?.(activeBlock.id, html);
              }}
            />
          ) : null}
        </ToolbarGroup>

        <ToolbarGroup>
          <Popover
            open={aiOpen}
            trigger="click"
            placement="bottom"
            content={reworkMutation.isPending ? <Spin size="small" /> : aiPopover}
            onOpenChange={(open) => {
              if (canUseAi) {
                setAiOpen(open);
              }
            }}
          >
            <Tooltip title={canUseAi ? 'Переработать выделенный текст (YandexGPT)' : 'Выделите текст'}>
              <Button
                type="text"
                size="small"
                icon={<RobotOutlined />}
                disabled={!canUseAi}
                onMouseDown={keepEditorSelection}
              />
            </Tooltip>
          </Popover>
        </ToolbarGroup>

        {onInsertImage ? (
          <ToolbarGroup>
            <Tooltip title="Вставить изображение">
              <Button
                type="text"
                size="small"
                icon={<PictureOutlined />}
                onClick={onInsertImage}
              />
            </Tooltip>
          </ToolbarGroup>
        ) : null}
      </div>
    </div>
  );
}

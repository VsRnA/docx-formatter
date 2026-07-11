import type { ReactNode } from 'react';
import { Button, ColorPicker, Divider, Popover, Space, Tooltip } from 'antd';
import {
  AlignCenterOutlined,
  AlignLeftOutlined,
  AlignRightOutlined,
  DeleteColumnOutlined,
  DeleteOutlined,
  DeleteRowOutlined,
  InsertRowAboveOutlined,
  InsertRowBelowOutlined,
  InsertRowLeftOutlined,
  InsertRowRightOutlined,
  MergeCellsOutlined,
  MoreOutlined,
  SplitCellsOutlined,
} from '@ant-design/icons';
import type { Editor } from '@tiptap/react';
import { BubbleMenu } from '@tiptap/react/menus';
import { keepEditorSelection } from '../lib/toolbarCommand';
import './TableBubbleMenu.css';

interface Props {
  editor: Editor;
}

function deleteTableBlock(editor: Editor) {
  const { $from } = editor.state.selection;

  for (let depth = $from.depth; depth >= 0; depth -= 1) {
    if ($from.node(depth).type.name === 'tableDocBlock') {
      const from = $from.before(depth);
      const to = $from.after(depth);
      editor.chain().focus().deleteRange({ from, to }).run();
      return;
    }
  }

  editor.chain().focus().deleteTable().run();
}

function setTableAlign(editor: Editor, align: 'left' | 'center' | 'right') {
  const { $from } = editor.state.selection;

  for (let depth = $from.depth; depth >= 0; depth -= 1) {
    if ($from.node(depth).type.name === 'tableDocBlock') {
      const pos = $from.before(depth);
      const node = editor.state.doc.nodeAt(pos);
      if (!node) {
        return;
      }

      const style = `margin-left:${align === 'left' ? '0' : 'auto'};margin-right:${align === 'right' ? '0' : 'auto'};${
        align === 'center' ? 'margin-left:auto;margin-right:auto;' : ''
      }`;

      editor.view.dispatch(
        editor.state.tr.setNodeMarkup(pos, undefined, {
          ...node.attrs,
          tableAlign: align,
        }),
      );

      const tablePos = pos + 1;
      const tableNode = editor.state.doc.nodeAt(tablePos);
      if (tableNode?.type.name === 'table') {
        editor.view.dispatch(
          editor.state.tr.setNodeMarkup(tablePos, undefined, {
            ...tableNode.attrs,
            style,
          }),
        );
      }

      return;
    }
  }
}

function setCellBackground(editor: Editor, color: string) {
  editor.chain().focus().setCellAttribute('backgroundColor', color).run();
}

function TableToolbarButton({
  title,
  icon,
  danger,
  onClick,
}: {
  title: string;
  icon: ReactNode;
  danger?: boolean;
  onClick: () => void;
}) {
  return (
    <Tooltip title={title}>
      <Button
        type="text"
        size="small"
        danger={danger}
        icon={icon}
        onMouseDown={keepEditorSelection}
        onClick={onClick}
      />
    </Tooltip>
  );
}

function TableMoreMenu({ editor }: { editor: Editor }) {
  return (
    <div className="document-table-bubble-menu__more">
      <Space direction="vertical" size={4} style={{ width: '100%' }}>
        <Button
          size="small"
          block
          onMouseDown={keepEditorSelection}
          onClick={() => editor.chain().focus().toggleHeaderRow().run()}
        >
          Заголовок строки
        </Button>
        <Button
          size="small"
          block
          onMouseDown={keepEditorSelection}
          onClick={() => editor.chain().focus().toggleHeaderColumn().run()}
        >
          Заголовок столбца
        </Button>
        <Popover
          trigger="click"
          placement="rightTop"
          content={
            <ColorPicker
              showText
              onChangeComplete={(color) => setCellBackground(editor, color.toHexString())}
            />
          }
        >
          <Button size="small" block onMouseDown={keepEditorSelection}>
            Заливка ячейки
          </Button>
        </Popover>
        <Divider style={{ margin: '4px 0' }} />
        <Space size={4}>
          <Tooltip title="Таблица слева">
            <Button
              type="text"
              size="small"
              icon={<AlignLeftOutlined />}
              onMouseDown={keepEditorSelection}
              onClick={() => setTableAlign(editor, 'left')}
            />
          </Tooltip>
          <Tooltip title="Таблица по центру">
            <Button
              type="text"
              size="small"
              icon={<AlignCenterOutlined />}
              onMouseDown={keepEditorSelection}
              onClick={() => setTableAlign(editor, 'center')}
            />
          </Tooltip>
          <Tooltip title="Таблица справа">
            <Button
              type="text"
              size="small"
              icon={<AlignRightOutlined />}
              onMouseDown={keepEditorSelection}
              onClick={() => setTableAlign(editor, 'right')}
            />
          </Tooltip>
        </Space>
      </Space>
    </div>
  );
}

export function TableBubbleMenu({ editor }: Props) {
  return (
    <BubbleMenu
      editor={editor}
      shouldShow={({ editor: currentEditor }) => currentEditor.isActive('table')}
      className="document-table-bubble-menu"
    >
      <div className="document-table-bubble-menu__row">
        <TableToolbarButton
          title="Строка выше"
          icon={<InsertRowAboveOutlined />}
          onClick={() => editor.chain().focus().addRowBefore().run()}
        />
        <TableToolbarButton
          title="Строка ниже"
          icon={<InsertRowBelowOutlined />}
          onClick={() => editor.chain().focus().addRowAfter().run()}
        />
        <TableToolbarButton
          title="Удалить строку"
          icon={<DeleteRowOutlined />}
          danger
          onClick={() => editor.chain().focus().deleteRow().run()}
        />

        <Divider type="vertical" className="document-table-bubble-menu__divider" />

        <TableToolbarButton
          title="Колонка слева"
          icon={<InsertRowLeftOutlined />}
          onClick={() => editor.chain().focus().addColumnBefore().run()}
        />
        <TableToolbarButton
          title="Колонка справа"
          icon={<InsertRowRightOutlined />}
          onClick={() => editor.chain().focus().addColumnAfter().run()}
        />
        <TableToolbarButton
          title="Удалить колонку"
          icon={<DeleteColumnOutlined />}
          danger
          onClick={() => editor.chain().focus().deleteColumn().run()}
        />

        <Divider type="vertical" className="document-table-bubble-menu__divider" />

        <TableToolbarButton
          title="Объединить"
          icon={<MergeCellsOutlined />}
          onClick={() => editor.chain().focus().mergeCells().run()}
        />
        <TableToolbarButton
          title="Разделить"
          icon={<SplitCellsOutlined />}
          onClick={() => editor.chain().focus().splitCell().run()}
        />

        <Divider type="vertical" className="document-table-bubble-menu__divider" />

        <Popover trigger="click" placement="bottom" content={<TableMoreMenu editor={editor} />}>
          <Tooltip title="Ещё">
            <Button
              type="text"
              size="small"
              icon={<MoreOutlined />}
              onMouseDown={keepEditorSelection}
            />
          </Tooltip>
        </Popover>

        <TableToolbarButton
          title="Удалить таблицу"
          icon={<DeleteOutlined />}
          danger
          onClick={() => deleteTableBlock(editor)}
        />
      </div>
    </BubbleMenu>
  );
}

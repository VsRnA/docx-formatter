import { useEffect } from 'react';
import type { Editor } from '@tiptap/react';
import { CellSelection } from '@tiptap/pm/tables';
import './TableGutterOverlay.css';

interface Props {
  editor: Editor;
}

function findTableBlockElement(editor: Editor): HTMLElement | null {
  const { $from } = editor.state.selection;
  for (let depth = $from.depth; depth >= 0; depth -= 1) {
    if ($from.node(depth).type.name === 'tableDocBlock') {
      const dom = editor.view.nodeDOM($from.before(depth));
      if (dom instanceof HTMLElement) {
        return dom;
      }
    }
  }

  return null;
}

export function TableGutterOverlay({ editor }: Props) {
  useEffect(() => {
    const render = () => {
      document.querySelectorAll('.document-table-gutters').forEach((node) => node.remove());

      if (!editor.isActive('table')) {
        return;
      }

      const tableBlock = findTableBlockElement(editor);
      const table = tableBlock?.querySelector('table');
      if (!tableBlock || !table) {
        return;
      }

      tableBlock.classList.add('doc-flow-block--table-active');

      const rows = Array.from(table.querySelectorAll('tr'));
      const firstRowCells = rows[0] ? Array.from(rows[0].children) : [];

      const container = document.createElement('div');
      container.className = 'document-table-gutters';

      const rowGutter = document.createElement('div');
      rowGutter.className = 'document-table-gutters__row-gutter';
      rows.forEach((row, rowIndex) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'document-table-gutters__button document-table-gutters__button--row';
        button.title = `Выделить строку ${rowIndex + 1}`;
        button.style.height = `${Math.max(row.getBoundingClientRect().height, 18)}px`;
        button.addEventListener('mousedown', (event) => {
          event.preventDefault();
          const cell = row.children[0];
          if (!cell) return;
          const cellPos = editor.view.posAtDOM(cell, 0);
          const $cell = editor.state.doc.resolve(cellPos);
          editor.view.dispatch(
            editor.state.tr.setSelection(CellSelection.rowSelection($cell)),
          );
          editor.commands.focus();
        });
        rowGutter.appendChild(button);
      });

      const colGutter = document.createElement('div');
      colGutter.className = 'document-table-gutters__col-gutter';
      firstRowCells.forEach((cell, colIndex) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'document-table-gutters__button document-table-gutters__button--col';
        button.title = `Выделить столбец ${colIndex + 1}`;
        button.style.width = `${Math.max(cell.getBoundingClientRect().width, 24)}px`;
        button.addEventListener('mousedown', (event) => {
          event.preventDefault();
          const cellPos = editor.view.posAtDOM(cell, 0);
          const $cell = editor.state.doc.resolve(cellPos);
          editor.view.dispatch(
            editor.state.tr.setSelection(CellSelection.colSelection($cell)),
          );
          editor.commands.focus();
        });
        colGutter.appendChild(button);
      });

      container.appendChild(rowGutter);
      container.appendChild(colGutter);
      tableBlock.appendChild(container);
    };

    render();
    editor.on('selectionUpdate', render);
    editor.on('transaction', render);
    window.addEventListener('resize', render);

    return () => {
      editor.off('selectionUpdate', render);
      editor.off('transaction', render);
      window.removeEventListener('resize', render);
      document.querySelectorAll('.document-table-gutters').forEach((node) => node.remove());
      document
        .querySelectorAll('.doc-flow-block--table-active')
        .forEach((node) => node.classList.remove('doc-flow-block--table-active'));
    };
  }, [editor]);

  return null;
}

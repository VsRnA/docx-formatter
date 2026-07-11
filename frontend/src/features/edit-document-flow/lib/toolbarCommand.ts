import type { Editor } from '@tiptap/react';
import type { MouseEvent as ReactMouseEvent } from 'react';

export function keepEditorSelection(event: ReactMouseEvent) {
  event.preventDefault();
}

export function runToolbarCommand(
  event: ReactMouseEvent,
  editor: Editor | null,
  run: (currentEditor: Editor) => void,
) {
  event.preventDefault();

  if (!editor || editor.isDestroyed) {
    return;
  }

  run(editor);
}

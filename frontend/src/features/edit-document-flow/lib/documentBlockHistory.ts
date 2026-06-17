import type { DocumentBlock } from '@/entities/block';

export interface ImageInsertAction {
  type: 'insert-image';
  block: DocumentBlock;
  afterBlockId: string | null;
}

export function createBlockHistory() {
  const undoStack: ImageInsertAction[] = [];
  const redoStack: ImageInsertAction[] = [];
  let lastMutation: 'block' | 'text' = 'text';

  return {
    recordImageInsert(action: ImageInsertAction) {
      undoStack.push(action);
      redoStack.length = 0;
      lastMutation = 'block';
    },

    notifyTextInput() {
      lastMutation = 'text';
    },

    canTryBlockUndo() {
      return lastMutation === 'block' && undoStack.length > 0;
    },

    canTryBlockRedo() {
      return lastMutation === 'block' && redoStack.length > 0;
    },

    peekUndo() {
      return undoStack[undoStack.length - 1] ?? null;
    },

    popUndo() {
      const action = undoStack.pop();
      if (!action) {
        return null;
      }
      redoStack.push(action);
      lastMutation = 'block';
      return action;
    },

    popRedo() {
      const action = redoStack.pop();
      if (!action) {
        return null;
      }
      undoStack.push(action);
      lastMutation = 'block';
      return action;
    },

    clear() {
      undoStack.length = 0;
      redoStack.length = 0;
      lastMutation = 'text';
    },
  };
}

export type BlockHistory = ReturnType<typeof createBlockHistory>;

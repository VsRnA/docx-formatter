import { Extension } from '@tiptap/core';

export const TabKeyboardExtension = Extension.create({
  name: 'tabKeyboard',

  addKeyboardShortcuts() {
    return {
      Tab: () => {
        if (this.editor.isActive('table')) {
          return false;
        }

        if (this.editor.can().sinkListItem('listItem')) {
          return this.editor.chain().focus().sinkListItem('listItem').run();
        }

        return this.editor.chain().focus().insertContent('\t').run();
      },
      'Shift-Tab': () => {
        if (this.editor.isActive('table')) {
          return false;
        }

        if (this.editor.can().liftListItem('listItem')) {
          return this.editor.chain().focus().liftListItem('listItem').run();
        }

        return true;
      },
    };
  },
});

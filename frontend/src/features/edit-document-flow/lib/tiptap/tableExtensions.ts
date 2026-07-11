import { Table } from '@tiptap/extension-table';
import TableRow from '@tiptap/extension-table-row';
import TableCell from '@tiptap/extension-table-cell';
import TableHeader from '@tiptap/extension-table-header';

function parseStyleAttribute(element: HTMLElement): string | null {
  return element.getAttribute('style');
}

function renderStyleAttribute(style: string | null | undefined) {
  if (!style) {
    return {};
  }

  return { style };
}

export const DocumentTable = Table.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      class: {
        default: null,
        parseHTML: (element) => element.getAttribute('class'),
        renderHTML: (attributes) => {
          const className = attributes.class ?? 'doc-table';

          return { class: className };
        },
      },
      style: {
        default: null,
        parseHTML: (element) => parseStyleAttribute(element),
        renderHTML: (attributes) => renderStyleAttribute(attributes.style),
      },
    };
  },
});

export const DocumentTableCell = TableCell.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      style: {
        default: null,
        parseHTML: (element) => parseStyleAttribute(element),
        renderHTML: (attributes) => renderStyleAttribute(attributes.style),
      },
      backgroundColor: {
        default: null,
        parseHTML: (element) => element.style.backgroundColor || element.getAttribute('data-background-color'),
        renderHTML: (attributes) => {
          if (!attributes.backgroundColor) {
            return {};
          }

          const style = attributes.style
            ? `${attributes.style};background-color:${attributes.backgroundColor}`
            : `background-color:${attributes.backgroundColor}`;

          return {
            style,
            'data-background-color': attributes.backgroundColor,
          };
        },
      },
    };
  },
});

export const DocumentTableHeader = TableHeader.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      style: {
        default: null,
        parseHTML: (element) => parseStyleAttribute(element),
        renderHTML: (attributes) => renderStyleAttribute(attributes.style),
      },
      backgroundColor: {
        default: null,
        parseHTML: (element) => element.style.backgroundColor || element.getAttribute('data-background-color'),
        renderHTML: (attributes) => {
          if (!attributes.backgroundColor) {
            return {};
          }

          const style = attributes.style
            ? `${attributes.style};background-color:${attributes.backgroundColor}`
            : `background-color:${attributes.backgroundColor}`;

          return {
            style,
            'data-background-color': attributes.backgroundColor,
          };
        },
      },
    };
  },
});

export function getTableExtensions() {
  return [
    DocumentTable.configure({
      resizable: true,
    }),
    TableRow,
    DocumentTableHeader,
    DocumentTableCell,
  ];
}

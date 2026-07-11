import { useState } from 'react';
import { Checkbox, InputNumber, Modal, Space } from 'antd';

export interface InsertTableOptions {
  rows: number;
  cols: number;
  withHeaderRow: boolean;
}

interface Props {
  open: boolean;
  onCancel: () => void;
  onInsert: (options: InsertTableOptions) => void;
}

export function InsertTableDialog({ open, onCancel, onInsert }: Props) {
  const [rows, setRows] = useState(3);
  const [cols, setCols] = useState(3);
  const [withHeaderRow, setWithHeaderRow] = useState(true);

  return (
    <Modal
      title="Вставить таблицу"
      open={open}
      okText="Вставить"
      cancelText="Отмена"
      onCancel={onCancel}
      onOk={() => onInsert({ rows, cols, withHeaderRow })}
      destroyOnClose
    >
      <Space direction="vertical" size={16} style={{ width: '100%' }}>
        <Space wrap>
          <span>Строки:</span>
          <InputNumber min={1} max={20} value={rows} onChange={(value) => setRows(value ?? 1)} />
          <span>Столбцы:</span>
          <InputNumber min={1} max={10} value={cols} onChange={(value) => setCols(value ?? 1)} />
        </Space>
        <Checkbox checked={withHeaderRow} onChange={(event) => setWithHeaderRow(event.target.checked)}>
          Заголовочная строка
        </Checkbox>
      </Space>
    </Modal>
  );
}

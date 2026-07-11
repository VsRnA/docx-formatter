import { Button, Space, Tooltip } from 'antd';
import {
  ArrowDownOutlined,
  ArrowUpOutlined,
  CopyOutlined,
  DeleteOutlined,
  SplitCellsOutlined,
} from '@ant-design/icons';
import { hasPageBreakBefore } from '../lib/blockPageBreak';
import type { DocumentBlock } from '@/entities/block';
import './BlockActionsToolbar.css';

interface Props {
  activeBlock: DocumentBlock | null;
  blocks: DocumentBlock[];
  onMoveUp: (blockId: string) => void;
  onMoveDown: (blockId: string) => void;
  onDuplicate: (blockId: string) => void;
  onDelete: (blockId: string) => void;
  onTogglePageBreak: (blockId: string, enabled: boolean) => void;
}

export function BlockActionsToolbar({
  activeBlock,
  blocks,
  onMoveUp,
  onMoveDown,
  onDuplicate,
  onDelete,
  onTogglePageBreak,
}: Props) {
  if (!activeBlock) {
    return null;
  }

  const index = blocks.findIndex((block) => block.id === activeBlock.id);
  const pageBreakEnabled = hasPageBreakBefore(activeBlock);

  return (
    <div className="block-actions-toolbar">
      <Space size={4} wrap>
        <Tooltip title="Переместить блок выше">
          <Button
            size="small"
            icon={<ArrowUpOutlined />}
            disabled={index <= 0}
            onClick={() => onMoveUp(activeBlock.id)}
          />
        </Tooltip>
        <Tooltip title="Переместить блок ниже">
          <Button
            size="small"
            icon={<ArrowDownOutlined />}
            disabled={index < 0 || index >= blocks.length - 1}
            onClick={() => onMoveDown(activeBlock.id)}
          />
        </Tooltip>
        <Tooltip title="Дублировать блок">
          <Button size="small" icon={<CopyOutlined />} onClick={() => onDuplicate(activeBlock.id)} />
        </Tooltip>
        <Tooltip title={pageBreakEnabled ? 'Убрать разрыв страницы' : 'Разрыв страницы перед блоком'}>
          <Button
            size="small"
            type={pageBreakEnabled ? 'primary' : 'default'}
            icon={<SplitCellsOutlined />}
            onClick={() => onTogglePageBreak(activeBlock.id, !pageBreakEnabled)}
          />
        </Tooltip>
        <Tooltip title="Удалить блок">
          <Button
            size="small"
            danger
            icon={<DeleteOutlined />}
            onClick={() => onDelete(activeBlock.id)}
          />
        </Tooltip>
      </Space>
    </div>
  );
}

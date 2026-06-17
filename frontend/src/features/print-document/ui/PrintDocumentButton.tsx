import { Button } from 'antd';
import { PrinterOutlined } from '@ant-design/icons';

interface Props {
  size?: 'small' | 'middle' | 'large';
}

export function PrintDocumentButton({ size = 'middle' }: Props) {
  return (
    <Button size={size} icon={<PrinterOutlined />} onClick={() => window.print()}>
      Печать / PDF
    </Button>
  );
}

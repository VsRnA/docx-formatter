import { Button, type ButtonProps, message } from 'antd';
import { documentApi } from '@/entities/document';

interface Props {
  documentId: string;
  size?: ButtonProps['size'];
}

export function ExportHtmlButton({ documentId, size }: Props) {
  return (
    <Button
      size={size}
      onClick={() => {
        window.open(documentApi.exportHtmlUrl(documentId), '_blank');
        message.success('Скачивание HTML началось');
      }}
    >
      Экспорт HTML
    </Button>
  );
}

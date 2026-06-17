import { Button, type ButtonProps, message } from 'antd';
import { DownloadOutlined } from '@ant-design/icons';
import { documentApi } from '@/entities/document';

interface Props {
  documentId: string;
  available?: boolean;
  size?: ButtonProps['size'];
}

export function DownloadTranslatedDocxButton({ documentId, available = false, size }: Props) {
  if (!available) {
    return null;
  }

  return (
    <Button
      size={size}
      icon={<DownloadOutlined />}
      onClick={() => {
        window.open(documentApi.translatedDocxUrl(documentId), '_blank');
        message.success('Скачивание переведённого DOCX началось');
      }}
    >
      Перевод DOCX
    </Button>
  );
}

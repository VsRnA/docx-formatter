import { Button, type ButtonProps } from 'antd';
import { useMutation } from '@tanstack/react-query';
import { message } from 'antd';
import { documentApi } from '@/entities/document';

interface Props {
  documentId: string;
  size?: ButtonProps['size'];
}

export function PublishButton({ documentId, size }: Props) {
  const mutation = useMutation({
    mutationFn: () => documentApi.publish(documentId).then((r) => r.data.data),
    onSuccess: (doc) => {
      message.success('Документ опубликован');
      if (doc.slug) {
        window.open(`/p/${doc.slug}`, '_blank');
      }
    },
    onError: (err: Error) => message.error(err.message),
  });

  return (
    <Button type="primary" size={size} loading={mutation.isPending} onClick={() => mutation.mutate()}>
      Опубликовать
    </Button>
  );
}

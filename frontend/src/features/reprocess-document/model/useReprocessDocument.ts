import { useMutation } from '@tanstack/react-query';
import { message } from 'antd';
import { documentApi } from '@/entities/document';

export function useReprocessDocument(documentId: string) {
  return useMutation({
    mutationFn: () => documentApi.reprocess(documentId).then((r) => r.data.data),
    onSuccess: () => message.success('Обработка перезапущена'),
    onError: (err: Error) => message.error(err.message),
  });
}

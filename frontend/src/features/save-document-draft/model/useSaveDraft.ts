import { useMutation, useQueryClient } from '@tanstack/react-query';
import { message } from 'antd';
import { documentApi, type SaveDraftBlockPayload } from '@/entities/document';

export function useSaveDraft(documentId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (blocks: SaveDraftBlockPayload[]) =>
      documentApi.saveDraft(documentId, blocks).then((r) => r.data.data),
    onSuccess: () => {
      message.success('Черновик сохранён');
      queryClient.invalidateQueries({ queryKey: ['document-editor', documentId] });
    },
    onError: (err: Error) => message.error(err.message),
  });
}

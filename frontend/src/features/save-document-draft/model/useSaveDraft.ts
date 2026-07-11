import { useMutation, useQueryClient } from '@tanstack/react-query';
import { message } from 'antd';
import { documentApi, type SaveDraftBlockPayload } from '@/entities/document';

export function useSaveDraft(documentId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (blocks: SaveDraftBlockPayload[]) =>
      documentApi.saveDraft(documentId, blocks).then((r) => r.data.data),
    onSuccess: (_document, variables) => {
      message.success('Черновик сохранён');
      queryClient.setQueryData(['document-editor', documentId], (current: unknown) => {
        if (!current || typeof current !== 'object') {
          return current;
        }

        const payload = current as {
          document?: { status?: string };
          blocks?: Array<{ id: string; html?: string | null; sort?: number }>;
          resources?: unknown[];
        };

        return {
          ...payload,
          document: payload.document
            ? { ...payload.document, status: 'draft' }
            : payload.document,
          blocks: variables
            .map((update) => {
              const existing = Array.isArray(payload.blocks)
                ? payload.blocks.find((block) => block.id === update.id)
                : undefined;

              return existing
                ? {
                    ...existing,
                    html: update.html ?? existing.html,
                    sort: update.sort ?? existing.sort,
                  }
                : {
                    id: update.id,
                    html: update.html,
                    sort: update.sort,
                  };
            })
            .sort((left, right) => (left.sort ?? 0) - (right.sort ?? 0)),
        };
      });
    },
    onError: (err: Error) => message.error(err.message),
  });
}

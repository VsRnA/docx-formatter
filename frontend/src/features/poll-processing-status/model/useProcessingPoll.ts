import { useQuery } from '@tanstack/react-query';
import { documentApi } from '@/entities/document';
import { TERMINAL_DOCUMENT_STATUSES } from '@/shared/constants';

const TERMINAL = new Set<string>(TERMINAL_DOCUMENT_STATUSES);

export function useProcessingPoll(documentId: string, enabled = true) {
  return useQuery({
    queryKey: ['document-status', documentId],
    queryFn: () => documentApi.status(documentId).then((r) => r.data),
    enabled: enabled && Boolean(documentId),
    refetchInterval: (query) => {
      const status = query.state.data?.status;
      if (!status || TERMINAL.has(status)) {
        return false;
      }
      return 2000;
    },
  });
}

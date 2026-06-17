import type { DocumentBlock } from '@/entities/block';

export function sortBlocks(blocks: DocumentBlock[]): DocumentBlock[] {
  return [...blocks].sort((a, b) => a.sort - b.sort);
}

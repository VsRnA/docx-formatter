import type { DocumentBlock } from '../model/types';

export function sortBlocks(blocks: DocumentBlock[]): DocumentBlock[] {
  return [...blocks].sort((a, b) => a.sort - b.sort);
}

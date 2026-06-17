import { useEffect, useState, type RefObject } from 'react';
import { DEFAULT_TOOLBAR_STATE, readToolbarState } from '../lib/editorCommands';

export function useFormatToolbarState(editorRef: RefObject<HTMLDivElement | null>) {
  const [state, setState] = useState(DEFAULT_TOOLBAR_STATE);

  useEffect(() => {
    const update = () => {
      setState(readToolbarState(editorRef.current));
    };

    document.addEventListener('selectionchange', update);
    return () => document.removeEventListener('selectionchange', update);
  }, [editorRef]);

  return state;
}

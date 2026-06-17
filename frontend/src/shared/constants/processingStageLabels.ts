import { ProcessingStages } from './processingStages';

export type ProcessingStage = (typeof ProcessingStages)[keyof typeof ProcessingStages];

export const PROCESSING_STAGE_LABELS: Record<ProcessingStage, string> = {
  [ProcessingStages.QUEUED]: 'в очереди',
  [ProcessingStages.DOWNLOAD]: 'загрузка',
  [ProcessingStages.PARSE]: 'разбор',
  [ProcessingStages.VALIDATE]: 'проверка',
  [ProcessingStages.NORMALIZE]: 'нормализация',
  [ProcessingStages.TRANSLATE]: 'перевод',
  [ProcessingStages.WRITE_DOCX]: 'docx',
  [ProcessingStages.BUILD_HTML]: 'html',
  [ProcessingStages.COMPLETED]: 'готово',
  [ProcessingStages.FAILED]: 'ошибка',
};

export function processingStageLabel(stage: string | null | undefined): string {
  if (!stage) {
    return '—';
  }

  return PROCESSING_STAGE_LABELS[stage as ProcessingStage] ?? stage;
}

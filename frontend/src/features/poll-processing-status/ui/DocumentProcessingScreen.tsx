import { Button, Steps, Typography } from 'antd';
import { ProcessingStages } from '@/shared/constants/processingStages';
import './DocumentProcessingScreen.css';

interface ParseCoverage {
  coverage_ratio?: number;
  passes_threshold?: boolean;
}

interface Props {
  stage?: string | null;
  parseCoverage?: ParseCoverage | null;
  onReprocess?: () => void;
  reprocessing?: boolean;
}

const STAGE_STEPS = [
  { key: ProcessingStages.QUEUED, title: 'В очереди' },
  { key: ProcessingStages.DOWNLOAD, title: 'Загрузка' },
  { key: ProcessingStages.PARSE, title: 'Разбор' },
  { key: ProcessingStages.VALIDATE, title: 'Проверка' },
  { key: ProcessingStages.NORMALIZE, title: 'AI-нормализация' },
  { key: ProcessingStages.BUILD_HTML, title: 'HTML' },
  { key: ProcessingStages.COMPLETED, title: 'Готово' },
];

function stageIndex(stage: string | null | undefined): number {
  if (!stage || stage === ProcessingStages.QUEUED) {
    return 0;
  }
  if (stage === ProcessingStages.WRITE_DOCX || stage === ProcessingStages.TRANSLATE) {
    return 3;
  }
  if (stage === ProcessingStages.NORMALIZE) {
    return 4;
  }
  const idx = STAGE_STEPS.findIndex((s) => s.key === stage);
  return idx >= 0 ? idx : 0;
}

export function DocumentProcessingScreen({
  stage,
  parseCoverage,
  onReprocess,
  reprocessing,
}: Props) {
  const current = stageIndex(stage);
  const coveragePct =
    parseCoverage?.coverage_ratio != null
      ? Math.round(parseCoverage.coverage_ratio * 1000) / 10
      : null;

  return (
    <div className="document-processing-screen" role="status" aria-label="Обработка документа">
      <div className="document-processing-screen__panel">
        <Typography.Title level={4} style={{ marginTop: 0, textAlign: 'center' }}>
          Обработка документа
        </Typography.Title>
        <Steps
          size="small"
          current={current}
          items={STAGE_STEPS.map((s) => ({ title: s.title }))}
          className="document-processing-screen__steps"
        />
        {coveragePct != null ? (
          <Typography.Paragraph type="secondary" style={{ textAlign: 'center', marginBottom: 0 }}>
            Полнота разбора: {coveragePct}%
            {!parseCoverage?.passes_threshold ? ' — требуется проверка' : ''}
          </Typography.Paragraph>
        ) : null}
        {onReprocess ? (
          <div className="document-processing-screen__actions">
            <Button onClick={onReprocess} loading={reprocessing} size="small">
              Перезапустить обработку
            </Button>
          </div>
        ) : null}
      </div>
    </div>
  );
}

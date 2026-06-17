import { unwrapDocumentRootHtml } from '@/shared/lib/documentRootHtml';
import { documentLayoutStyleVars, type DocumentLayout } from '@/shared/lib/documentLayoutStyle';
import './DocumentPageView.css';

interface Props {
  html: string;
  className?: string;
  layout?: DocumentLayout | null;
}

/** A4 page shell matching DocumentFlowEditor layout (without editor chrome). */
export function DocumentPageView({ html, className, layout }: Props) {
  const innerHtml = unwrapDocumentRootHtml(html);
  const layoutStyle = documentLayoutStyleVars(layout);

  return (
    <div className={['document-page', className].filter(Boolean).join(' ')}>
      <div className="document-page-frame" style={layoutStyle}>
        <article
          className="document-root"
          style={layoutStyle}
          dangerouslySetInnerHTML={{ __html: innerHtml }}
        />
      </div>
    </div>
  );
}

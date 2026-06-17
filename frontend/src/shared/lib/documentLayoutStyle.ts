import type { CSSProperties } from 'react';

export interface DocumentSectionLayout {
  page_width_mm: number;
  page_height_mm: number;
  margin_top_mm: number;
  margin_right_mm: number;
  margin_bottom_mm: number;
  margin_left_mm: number;
  columns?: number;
}

export interface DocumentDefaultsLayout {
  font?: string | null;
  size_pt?: number | null;
  line_height?: number | null;
  color?: string | null;
}

export interface DocumentLayout {
  section?: DocumentSectionLayout | null;
  defaults?: DocumentDefaultsLayout | null;
}

function quoteFontFamily(font: string): string {
  const trimmed = font.trim();
  if (trimmed === '') {
    return trimmed;
  }

  return trimmed.includes('"') || trimmed.includes("'") ? trimmed : `"${trimmed}"`;
}

export function documentLayoutStyleVars(layout?: DocumentLayout | null): CSSProperties {
  const style: Record<string, string> = {};
  const section = layout?.section;
  const defaults = layout?.defaults;

  if (section) {
    style['--document-page-width'] = `${section.page_width_mm}mm`;
    style['--document-page-height'] = `${section.page_height_mm}mm`;
    style['--document-page-margin-top'] = `${section.margin_top_mm}mm`;
    style['--document-page-margin-right'] = `${section.margin_right_mm}mm`;
    style['--document-page-margin-bottom'] = `${section.margin_bottom_mm}mm`;
    style['--document-page-margin-left'] = `${section.margin_left_mm}mm`;
  }

  if (defaults?.font) {
    const quoted = quoteFontFamily(defaults.font);
    style['--document-default-font'] =
      `${quoted}, 'DejaVu Serif', 'Liberation Serif', 'Times New Roman', Times, serif`;
  }

  if (defaults?.size_pt != null) {
    style['--document-default-size'] = `${defaults.size_pt}pt`;
  }

  if (defaults?.line_height != null) {
    style['--document-default-line-height'] = String(defaults.line_height);
  }

  if (defaults?.color) {
    style['--document-default-color'] = defaults.color.startsWith('#')
      ? defaults.color
      : `#${defaults.color}`;
  }

  return style as CSSProperties;
}

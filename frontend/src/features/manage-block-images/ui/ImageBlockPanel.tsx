import { useEffect, useState } from 'react';
import { Upload, Button, message, Input, Typography, Select } from 'antd';
import { InboxOutlined, DeleteOutlined } from '@ant-design/icons';
import type { DocumentBlock } from '@/entities/block';
import { resourceApi } from '@/entities/resource';
import { useMutation } from '@tanstack/react-query';
import {
  buildImageHtml,
  parseImageBlock,
  patchImageBlockHtml,
  resolveImageUrl,
  type ImageWrap,
} from '@/shared/lib/imageBlockHtml';
import { getImageUploadError } from '@/shared/lib/imageUploadValidation';
import './ImageBlockPanel.css';

interface Props {
  documentId: string;
  block: DocumentBlock;
  onBlockUpdate: (blockId: string, html: string, assets?: Record<string, unknown>) => void;
}

export function ImageBlockPanel({ documentId, block, onBlockUpdate }: Props) {
  const imageUrl = resolveImageUrl(block.html, block.assets_json);
  const [alt, setAlt] = useState(() => parseImageBlock(block.html).alt);
  const [caption, setCaption] = useState(() => parseImageBlock(block.html).caption);
  const [wrap, setWrap] = useState<ImageWrap>(() => parseImageBlock(block.html).wrap);
  const parsed = parseImageBlock(block.html);

  useEffect(() => {
    const nextParsed = parseImageBlock(block.html);
    setAlt(nextParsed.alt);
    setCaption(nextParsed.caption);
    setWrap(nextParsed.wrap);
  }, [block.id, block.html]);

  const upload = useMutation({
    mutationFn: (file: File) => resourceApi.uploadImage(documentId, file).then((r) => r.data.data),
    onSuccess: (resource) => {
      const nextUrl = resource.url ?? '';
      const html =
        patchImageBlockHtml(block.html, {
          src: nextUrl,
          alt,
          wrap,
          caption,
          align: parsed.align,
        }) || buildImageHtml(nextUrl, alt, parsed.align, false, wrap, caption);

      onBlockUpdate(block.id, html, {
        resource_id: resource.id,
        url: nextUrl,
      });
      message.success('Изображение загружено');
    },
    onError: (e: Error) => message.error(e.message),
  });

  const applyImageMeta = (nextAlt = alt, nextWrap = wrap, nextCaption = caption) => {
    const src = resolveImageUrl(block.html, block.assets_json);
    if (!src) {
      return;
    }

    const html = patchImageBlockHtml(block.html, {
      src,
      alt: nextAlt,
      wrap: nextWrap,
      caption: nextCaption,
      align: parsed.align,
    });

    onBlockUpdate(block.id, html, {
      ...(block.assets_json ?? {}),
      url: src,
    });
  };

  const uploadProps = {
    accept: 'image/png,image/jpeg,image/jpg,image/gif,image/webp',
    showUploadList: false as const,
    multiple: false as const,
    beforeUpload: (file: File) => {
      const validationError = getImageUploadError(file);
      if (validationError) {
        message.error(validationError);
        return false;
      }

      upload.mutate(file);
      return false;
    },
  };

  return (
    <div className="image-block-panel">
      <Typography.Title level={5} className="image-block-panel__title">
        Изображение
      </Typography.Title>
      <p className="image-block-panel__hint">
        Загрузите или замените файл. Выравнивание — кнопками на панели форматирования над документом.
      </p>

      {imageUrl ? (
        <div className="image-block-panel__preview">
          <img src={imageUrl} alt={alt || 'Превью'} />
        </div>
      ) : (
        <Upload.Dragger {...uploadProps} className="image-block-panel__dropzone">
          <p className="ant-upload-drag-icon">
            <InboxOutlined />
          </p>
          <p className="ant-upload-text">Перетащите изображение</p>
          <p className="ant-upload-hint">PNG, JPG, GIF, WEBP до 10 МБ. EMF/WMF не поддерживаются.</p>
        </Upload.Dragger>
      )}

      {imageUrl ? (
        <Upload {...uploadProps}>
          <Button block loading={upload.isPending} style={{ marginTop: 12 }}>
            Заменить изображение
          </Button>
        </Upload>
      ) : null}

      <div className="image-block-panel__field">
        <span className="image-block-panel__label">Подпись (alt)</span>
        <Input
          value={alt}
          placeholder="Краткое описание для доступности"
          onChange={(event) => {
            const nextAlt = event.target.value;
            setAlt(nextAlt);
            if (imageUrl) {
              applyImageMeta(nextAlt, wrap, caption);
            }
          }}
        />
      </div>

      <div className="image-block-panel__field">
        <span className="image-block-panel__label">Подпись под изображением</span>
        <Input
          value={caption}
          placeholder="Подпись (caption)"
          onChange={(event) => {
            const nextCaption = event.target.value;
            setCaption(nextCaption);
            if (imageUrl) {
              applyImageMeta(alt, wrap, nextCaption);
            }
          }}
        />
      </div>

      <div className="image-block-panel__field">
        <span className="image-block-panel__label">Обтекание текстом</span>
        <Select
          value={wrap}
          style={{ width: '100%' }}
          options={[
            { label: 'В строке', value: 'inline' },
            { label: 'Обтекание (квадрат)', value: 'square' },
            { label: 'Плотное обтекание', value: 'tight' },
          ]}
          onChange={(nextWrap) => {
            setWrap(nextWrap);
            if (imageUrl) {
              applyImageMeta(alt, nextWrap, caption);
            }
          }}
        />
      </div>

      {imageUrl ? (
        <Button
          type="text"
          danger
          block
          icon={<DeleteOutlined />}
          onClick={() => {
            setAlt('');
            onBlockUpdate(block.id, buildImageHtml('', '', 'left', true), {});
          }}
        >
          Убрать изображение
        </Button>
      ) : null}
    </div>
  );
}

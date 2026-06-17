import { useEffect, useState } from 'react';
import { Upload, Button, message, Input, Typography } from 'antd';
import { InboxOutlined, DeleteOutlined } from '@ant-design/icons';
import type { DocumentBlock } from '@/entities/block';
import { resourceApi } from '@/entities/resource';
import { useMutation } from '@tanstack/react-query';
import { buildImageHtml, parseImageBlock } from '@/shared/lib/imageBlockHtml';
import './ImageBlockPanel.css';

interface Props {
  documentId: string;
  block: DocumentBlock;
  onBlockUpdate: (blockId: string, html: string, assets?: Record<string, unknown>) => void;
}

export function ImageBlockPanel({ documentId, block, onBlockUpdate }: Props) {
  const [alt, setAlt] = useState(() => parseImageBlock(block.html).alt);
  const parsed = parseImageBlock(block.html);
  const imageUrl = (block.assets_json?.url as string | undefined) ?? parsed.src;

  useEffect(() => {
    setAlt(parseImageBlock(block.html).alt);
  }, [block.id, block.html]);

  const upload = useMutation({
    mutationFn: (file: File) => resourceApi.uploadImage(documentId, file).then((r) => r.data.data),
    onSuccess: (resource) => {
      const align = parseImageBlock(block.html).align;
      const html = buildImageHtml(resource.url ?? '', alt, align);
      onBlockUpdate(block.id, html, {
        resource_id: resource.id,
        url: resource.url,
      });
      message.success('Изображение загружено');
    },
    onError: (e: Error) => message.error(e.message),
  });

  const applyAlt = (nextAlt: string) => {
    if (!imageUrl) return;
    onBlockUpdate(
      block.id,
      buildImageHtml(imageUrl, nextAlt, parsed.align),
      {
        ...(block.assets_json ?? {}),
        url: imageUrl,
      },
    );
  };

  const uploadProps = {
    accept: 'image/*',
    showUploadList: false as const,
    multiple: false as const,
    beforeUpload: (file: File) => {
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
          <p className="ant-upload-hint">PNG, JPG, WEBP</p>
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
              applyAlt(nextAlt);
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

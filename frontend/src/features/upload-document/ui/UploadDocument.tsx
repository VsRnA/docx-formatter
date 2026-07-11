import { useState } from 'react';
import { InboxOutlined } from '@ant-design/icons';
import { message, Spin, Switch, Upload } from 'antd';
import type { UploadProps } from 'antd';
import { useMutation } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { documentApi } from '@/entities/document';
import { ROUTES } from '@/shared/config/env';
import { MAX_UPLOAD_MB } from '@/shared/constants';
import { validateDocxFile } from '@/shared/lib/validateDocxFile';
import './UploadDocument.css';

export function UploadDocument() {
  const navigate = useNavigate();
  const [isRussianDocument, setIsRussianDocument] = useState(false);

  const mutation = useMutation({
    mutationFn: ({ file, translate }: { file: File; translate: boolean }) =>
      documentApi.upload(file, undefined, translate).then((r) => r.data.data),
    onSuccess: (doc) => {
      navigate(ROUTES.documentEdit(doc.id));
    },
    onError: (err: Error) => message.error(err.message),
  });

  const props: UploadProps = {
    accept: '.docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    maxCount: 1,
    showUploadList: false,
    beforeUpload: (file) => {
      const error = validateDocxFile(file);
      if (error) {
        message.error(error);
        return Upload.LIST_IGNORE;
      }

      mutation.mutate({ file, translate: !isRussianDocument });
      return false;
    },
  };

  return (
    <div className={`upload-document${mutation.isPending ? ' upload-document--loading' : ''}`}>
      <div className="upload-document__options">
        <Switch
          checked={isRussianDocument}
          disabled={mutation.isPending}
          onChange={setIsRussianDocument}
        />
        <label className="upload-document__option-label">
          <span className="upload-document__option-title">Документ уже на русском</span>
          <span className="upload-document__option-hint">
            {isRussianDocument
              ? 'Перевод не выполняется — только разбор и подготовка к редактированию'
              : 'Текст будет переведён с английского через Yandex AI'}
          </span>
        </label>
      </div>

      <Upload.Dragger {...props} disabled={mutation.isPending} className="upload-dragger-branded">
        {mutation.isPending ? (
          <div className="upload-document__loading">
            <Spin size="large" className="upload-document__spinner" />
          </div>
        ) : (
          <>
            <p className="ant-upload-drag-icon">
              <InboxOutlined style={{ color: '#f26522', fontSize: 48 }} />
            </p>
            <p className="ant-upload-text">Перетащите .docx или нажмите для выбора</p>
            <p className="ant-upload-hint">
              Принимаются только документы Word (.docx), до {MAX_UPLOAD_MB} МБ. Другие форматы будут отклонены.
            </p>
          </>
        )}
      </Upload.Dragger>
    </div>
  );
}

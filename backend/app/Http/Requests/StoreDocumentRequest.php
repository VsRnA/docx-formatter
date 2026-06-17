<?php

namespace App\Http\Requests;

use App\Rules\DocxUploadFile;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxKb = (int) config('app.max_upload_mb', 50) * 1024;

        return [
            'file' => ['required', 'file', new DocxUploadFile($maxKb)],
            'title' => ['nullable', 'string', 'max:255'],
            'translate' => ['sometimes'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Выберите файл .docx',
            'file.file' => 'Загруженный объект должен быть файлом',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveDocumentDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'blocks' => ['required', 'array'],
            'blocks.*.id' => ['required', 'uuid'],
            'blocks.*.type' => ['required', 'string'],
            'blocks.*.sort' => ['required', 'integer', 'min:0'],
            'blocks.*.html' => ['nullable', 'string'],
            'blocks.*.styles' => ['nullable', 'array'],
            'blocks.*.meta' => ['nullable', 'array'],
            'blocks.*.assets' => ['nullable', 'array'],
        ];
    }
}

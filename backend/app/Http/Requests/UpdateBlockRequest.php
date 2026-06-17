<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'html' => ['sometimes', 'string'],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'styles_json' => ['sometimes', 'nullable', 'array'],
            'meta_json' => ['sometimes', 'nullable', 'array'],
        ];
    }
}

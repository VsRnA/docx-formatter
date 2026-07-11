<?php

namespace App\Http\Requests;

use App\Enums\BlockType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateBlockRequest extends FormRequest
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
            'type' => ['sometimes', Rule::enum(BlockType::class)],
            'sort' => ['sometimes', 'integer', 'min:0'],
            'html' => ['sometimes', 'string'],
            'text_original' => ['sometimes', 'nullable', 'string'],
            'styles_json' => ['sometimes', 'nullable', 'array'],
            'meta_json' => ['sometimes', 'nullable', 'array'],
            'assets_json' => ['sometimes', 'nullable', 'array'],
        ];
    }
}

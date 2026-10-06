<?php

namespace App\Http\Requests\Intern;

use App\Models\DocumentRequest;
use Illuminate\Foundation\Http\FormRequest;

class DocumentRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $request = $this->route('documentRequest');

        return $request instanceof DocumentRequest
            ? $this->user()->can('update', $request)
            : ($this->user()?->isIntern() ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['document_name' => trim((string) $this->input('document_name'))]);
    }

    public function rules(): array
    {
        return [
            'document_name' => ['required', 'string', 'max:150'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

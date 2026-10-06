<?php

namespace App\Http\Requests\Intern;

use Illuminate\Foundation\Http\FormRequest;

class SubmitDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('submit', $this->route('folder'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['title' => trim((string) $this->input('title'))]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'file' => ['required', 'file', 'mimetypes:application/pdf', 'max:'.config('wiis.uploads.max_pdf_kb')],
        ];
    }

    public function messages(): array
    {
        return ['file.mimetypes' => 'The document must be a PDF file.'];
    }
}

<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class FulfilDocumentRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('handle', $this->route('documentRequest'));
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimetypes:application/pdf', 'max:'.config('wiis.uploads.max_pdf_kb')]];
    }

    public function messages(): array
    {
        return ['file.mimetypes' => 'The document must be a PDF file.'];
    }
}

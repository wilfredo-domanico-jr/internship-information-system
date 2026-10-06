<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class IssueCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('placement'));
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimetypes:application/pdf', 'max:'.config('wiis.uploads.max_pdf_kb')]];
    }

    public function messages(): array
    {
        return ['file.mimetypes' => 'The certificate must be a PDF file.'];
    }
}

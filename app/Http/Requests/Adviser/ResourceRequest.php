<?php

namespace App\Http\Requests\Adviser;

use Illuminate\Foundation\Http\FormRequest;

class ResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('classSection'));
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
        return ['file.mimetypes' => 'The resource must be a PDF file.'];
    }
}

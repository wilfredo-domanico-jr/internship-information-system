<?php

namespace App\Http\Requests\Intern;

use Illuminate\Foundation\Http\FormRequest;

class ApplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isIntern() && $this->user()->can('view', $this->route('posting'));
    }

    public function rules(): array
    {
        $pdf = ['required', 'file', 'mimetypes:application/pdf', 'max:'.config('wiis.uploads.max_pdf_kb')];

        return ['resume' => $pdf, 'endorsement' => $pdf];
    }

    public function messages(): array
    {
        return [
            'resume.mimetypes' => 'Your resume must be a PDF file.',
            'endorsement.mimetypes' => 'The endorsement letter must be a PDF file.',
        ];
    }
}

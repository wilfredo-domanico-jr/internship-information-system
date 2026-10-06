<?php

namespace App\Http\Requests\Intern;

use Illuminate\Foundation\Http\FormRequest;

class SubmitDtrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isIntern() ?? false;
    }

    public function rules(): array
    {
        return [
            'period_from' => ['required', 'date', 'before_or_equal:today'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from', 'before_or_equal:today'],
            'hours' => ['required', 'integer', 'min:1', 'max:744'],
            'absences' => ['required', 'integer', 'min:0', 'max:31'],
            'file' => ['required', 'file', 'mimetypes:application/pdf', 'max:'.config('wiis.uploads.max_pdf_kb')],
        ];
    }

    public function messages(): array
    {
        return [
            'period_to.after_or_equal' => 'The period must end on or after it starts.',
            'period_to.before_or_equal' => 'You can only submit DTRs for days that have passed.',
            'file.mimetypes' => 'The DTR must be a PDF file.',
        ];
    }
}

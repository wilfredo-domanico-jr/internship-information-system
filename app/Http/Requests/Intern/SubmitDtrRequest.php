<?php

namespace App\Http\Requests\Intern;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty() || ! ($placement = $this->user()->activePlacement()->first())) {
                return;
            }

            $from = Carbon::parse($this->input('period_from'))->startOfDay();
            $days = $from->diffInDays(Carbon::parse($this->input('period_to'))->startOfDay()) + 1;

            if ((int) $this->input('hours') > $days * 24) {
                $v->errors()->add('hours', 'Hours cannot exceed 24 per day of the period.');
            }

            if ($from->lt($placement->started_at->copy()->startOfDay())) {
                $v->errors()->add('period_from', 'The period cannot start before your placement began.');
            }
        });
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

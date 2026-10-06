<?php

namespace App\Http\Requests\Adviser;

use App\Models\ClassSection;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ClassSectionRequest extends FormRequest
{
    public const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public function authorize(): bool
    {
        $section = $this->route('classSection');

        return $section instanceof ClassSection
            ? $this->user()->can('manage', $section)
            : ($this->user()?->isAdviser() ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'course_code' => Str::upper(trim((string) $this->input('course_code'))),
            'section' => Str::upper(trim((string) $this->input('section'))),
            'subject' => trim((string) $this->input('subject')),
            'school_year' => trim((string) $this->input('school_year')),
        ]);
    }

    public function rules(): array
    {
        return [
            'course_code' => ['required', 'string', 'max:30'],
            'subject' => ['required', 'string', 'max:255'],
            'section' => ['required', 'string', 'max:50'],
            'day' => ['required', Rule::in(self::DAYS)],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'school_year' => ['required', 'regex:/^\d{4}-\d{4}$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $current = $this->route('classSection');

            $duplicate = ClassSection::query()->active()
                ->where('course_code', $this->input('course_code'))
                ->where('section', $this->input('section'))
                ->where('school_year', $this->input('school_year'))
                ->when($current instanceof ClassSection, fn (Builder $q) => $q->whereKeyNot($current->id))
                ->exists();

            if ($duplicate) {
                $validator->errors()->add('section', 'An active class with this course code, section and school year already exists.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'school_year.regex' => 'The school year must look like 2025-2026.',
            'ends_at.after' => 'The end time must be after the start time.',
        ];
    }
}

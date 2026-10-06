<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('decide', $this->route('application'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['link' => $this->filled('link') ? trim((string) $this->input('link')) : null, 'notes' => $this->filled('notes') ? $this->input('notes') : null]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'venue' => ['required', 'string', 'max:255'],
            'link' => ['nullable', 'url', 'max:255'],
            'scheduled_on' => ['required', 'date', 'after_or_equal:today'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['scheduled_on.after_or_equal' => 'The interview date cannot be in the past.', 'ends_at.after' => 'The end time must be after the start time.'];
    }
}

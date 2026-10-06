<?php

namespace App\Http\Requests\Company;

use App\Models\InternshipPosting;
use Illuminate\Foundation\Http\FormRequest;

class PostingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $posting = $this->route('posting');

        return $posting instanceof InternshipPosting
            ? $this->user()->can('manage', $posting)
            : ($this->user()?->isCompany() ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title')),
            'closing_date' => $this->filled('closing_date') ? $this->input('closing_date') : null,
            'required_hours' => $this->filled('required_hours') ? $this->input('required_hours') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:5000'],
            'responsibilities' => ['nullable', 'string', 'max:5000'],
            'closing_date' => ['nullable', 'date', 'after_or_equal:today'],
            'required_hours' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'vacancies' => ['required', 'integer', 'min:1', 'max:100'],
            'contact_name' => ['required', 'string', 'max:100'],
            'contact_position' => ['nullable', 'string', 'max:100'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return ['closing_date.after_or_equal' => 'The closing date cannot be in the past.'];
    }
}

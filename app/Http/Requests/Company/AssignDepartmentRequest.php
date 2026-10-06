<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class AssignDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('placement'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['department_id' => $this->filled('department_id') ? $this->input('department_id') : null]);
    }

    public function rules(): array
    {
        return ['department_id' => ['nullable', 'integer', 'exists:departments,id']];
    }
}

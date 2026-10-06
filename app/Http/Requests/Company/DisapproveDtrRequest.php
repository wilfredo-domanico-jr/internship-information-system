<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class DisapproveDtrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('dtr'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['note' => trim((string) $this->input('note'))]);
    }

    public function rules(): array
    {
        return ['note' => ['required', 'string', 'max:500']];
    }

    public function messages(): array
    {
        return ['note.required' => 'Tell the intern why the DTR was disapproved.'];
    }
}

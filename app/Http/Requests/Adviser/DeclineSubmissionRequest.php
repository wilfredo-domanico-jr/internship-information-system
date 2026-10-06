<?php

namespace App\Http\Requests\Adviser;

use Illuminate\Foundation\Http\FormRequest;

class DeclineSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('submission'));
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
        return ['note.required' => 'Tell the intern why the document was declined.'];
    }
}

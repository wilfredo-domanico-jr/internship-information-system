<?php

namespace App\Http\Requests\Intern;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class JoinCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isIntern() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['company_code' => Str::upper(trim((string) $this->input('company_code')))]);
    }

    public function rules(): array
    {
        return ['company_code' => ['required', 'string', 'max:20']];
    }
}

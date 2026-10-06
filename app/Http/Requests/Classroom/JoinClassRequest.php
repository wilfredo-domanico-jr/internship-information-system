<?php

namespace App\Http\Requests\Classroom;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class JoinClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the portal's role middleware already limits who can reach it
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['join_code' => Str::upper(trim((string) $this->input('join_code')))]);
    }

    public function rules(): array
    {
        return ['join_code' => ['required', 'string', 'max:20']];
    }
}

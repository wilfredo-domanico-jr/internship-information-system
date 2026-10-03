<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        $pdf = ['required', 'file', 'mimetypes:application/pdf', 'max:'.config('wiis.uploads.max_pdf_kb')];

        return [
            'company_name' => ['required', 'string', 'max:255'],
            'company_type' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'about' => ['nullable', 'string', 'max:2000'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'permit' => $pdf,
            'moa' => $pdf,
            'terms' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'permit.mimetypes' => 'The business permit must be a PDF file.',
            'moa.mimetypes' => 'The MOA must be a PDF file.',
            'terms.accepted' => 'Please accept the terms and conditions.',
        ];
    }
}

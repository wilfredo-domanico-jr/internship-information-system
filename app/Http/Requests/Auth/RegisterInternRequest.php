<?php

namespace App\Http\Requests\Auth;

use App\Enums\ClassStatus;
use App\Models\ClassSection;
use App\Models\InternProfile;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterInternRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower(trim((string) $this->input('email'))),
            'join_code' => Str::upper(trim((string) $this->input('join_code'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'student_number' => ['required', 'string', 'max:30', Rule::unique(InternProfile::class, 'student_number')],
            'join_code' => ['required', 'string', Rule::exists(ClassSection::class, 'join_code')->where('status', ClassStatus::Active->value)],
            'password' => ['required', 'confirmed', Password::defaults()],
            'terms' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'join_code.exists' => 'We could not find an active class with that join code.',
            'terms.accepted' => 'Please accept the terms and conditions.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];

        if ($this->user()->isIntern()) {
            $rules += [
                'gender' => ['nullable', 'string', 'max:20'],
                'birthdate' => ['nullable', 'date', 'before:today'],
                'present_address' => ['nullable', 'string', 'max:255'],
                'permanent_address' => ['nullable', 'string', 'max:255'],
                'about' => ['nullable', 'string', 'max:2000'],
            ];
        }

        if ($this->user()->isCompany()) {
            $rules += [
                'company_name' => ['required', 'string', 'max:255'],
                'company_type' => ['required', 'string', 'max:100'],
                'website' => ['nullable', 'url', 'max:255'],
                'address' => ['required', 'string', 'max:255'],
                'about' => ['nullable', 'string', 'max:2000'],
            ];
        }

        return $rules;
    }
}

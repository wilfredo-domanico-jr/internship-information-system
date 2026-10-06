<?php

namespace App\Http\Requests\Adviser;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('classSection'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100',
                Rule::unique('class_folders', 'name')->where('class_section_id', $this->route('classSection')->id)],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'This class already has a folder with that name.'];
    }
}

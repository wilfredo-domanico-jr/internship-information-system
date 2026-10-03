<?php

namespace App\Http\Requests\Admin;

use App\Models\Department;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100',
                // Case-insensitive uniqueness on both SQLite and MySQL, ignoring the record being renamed.
                function (string $attribute, mixed $value, Closure $fail) {
                    $exists = Department::query()
                        ->whereRaw('lower(name) = ?', [mb_strtolower((string) $value)])
                        ->when($this->route('department'), fn ($q, $dept) => $q->whereKeyNot($dept->id))
                        ->exists();
                    if ($exists) {
                        $fail('A department with this name already exists.');
                    }
                },
            ],
        ];
    }
}

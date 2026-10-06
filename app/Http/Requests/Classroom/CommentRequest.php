<?php

namespace App\Http\Requests\Classroom;

use Illuminate\Foundation\Http\FormRequest;

class CommentRequest extends FormRequest
{
    protected $errorBag = 'comment';

    public function authorize(): bool
    {
        return $this->user()->can('comment', $this->route('announcement'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['body' => trim((string) $this->input('body'))]);
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:1000']];
    }
}

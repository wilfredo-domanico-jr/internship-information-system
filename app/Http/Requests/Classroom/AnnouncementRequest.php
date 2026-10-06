<?php

namespace App\Http\Requests\Classroom;

use App\Models\Announcement;
use App\Services\HtmlSanitizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $announcement = $this->route('announcement');

        return $announcement instanceof Announcement
            ? $this->user()->can('update', $announcement)
            : $this->user()->can('manage', $this->route('classSection'));
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:20000']];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $validator->errors()->has('body') && app(HtmlSanitizer::class)->isBlank($this->input('body'))) {
                $validator->errors()->add('body', 'Write something before posting.');
            }
        });
    }

    public function messages(): array
    {
        return ['body.required' => 'Write something before posting.'];
    }
}

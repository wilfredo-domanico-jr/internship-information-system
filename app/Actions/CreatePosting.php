<?php

namespace App\Actions;

use App\Enums\PostingStatus;
use App\Models\Company;
use App\Models\InternshipPosting;

class CreatePosting
{
    /** @param  array{title:string, city:string, description:string, responsibilities?:?string, closing_date?:?string, required_hours?:int|string|null, vacancies:int|string, contact_name:string, contact_position?:?string, contact_phone?:?string}  $data */
    public function __invoke(Company $company, array $data): InternshipPosting
    {
        return $company->postings()->create([...self::attributes($data), 'status' => PostingStatus::Open]);
    }

    /** @return array<string, mixed> */
    public static function attributes(array $data): array
    {
        return [
            'title' => $data['title'],
            'city' => $data['city'],
            'description' => $data['description'],
            'responsibilities' => $data['responsibilities'] ?? null,
            'closing_date' => $data['closing_date'] ?: null,
            'required_hours' => isset($data['required_hours']) && $data['required_hours'] !== '' ? (int) $data['required_hours'] : null,
            'vacancies' => (int) $data['vacancies'],
            'contact_name' => $data['contact_name'],
            'contact_position' => $data['contact_position'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
        ];
    }
}

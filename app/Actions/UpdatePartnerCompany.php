<?php

namespace App\Actions;

use App\Models\Company;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpdatePartnerCompany
{
    /** @param  array{name:string, type?:?string, about?:?string, website?:?string, address?:?string}  $data */
    public function __invoke(Company $company, array $data, ?UploadedFile $logo = null): Company
    {
        $attributes = [
            'name' => $data['name'],
            'type' => $data['type'] ?? null,
            'about' => $data['about'] ?? null,
            'website' => $data['website'] ?? null,
            'address' => $data['address'] ?? null,
        ];

        $previousLogo = $company->logo_path;

        if ($logo) {
            $attributes['logo_path'] = $logo->store('partner-logos', 'public');
        }

        $company->update($attributes);

        if ($logo && $previousLogo && Storage::disk('public')->exists($previousLogo)) {
            Storage::disk('public')->delete($previousLogo);
        }

        return $company;
    }
}

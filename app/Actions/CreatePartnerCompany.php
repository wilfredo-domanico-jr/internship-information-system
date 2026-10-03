<?php

namespace App\Actions;

use App\Enums\ApprovalStatus;
use App\Models\Company;
use App\Services\JoinCodeGenerator;
use Illuminate\Http\UploadedFile;

class CreatePartnerCompany
{
    public function __construct(private readonly JoinCodeGenerator $codes) {}

    /** @param  array{name:string, type?:?string, about?:?string, website?:?string, address?:?string}  $data */
    public function __invoke(array $data, ?UploadedFile $logo = null): Company
    {
        return Company::create([
            'user_id' => null,
            'name' => $data['name'],
            'type' => $data['type'] ?? null,
            'about' => $data['about'] ?? null,
            'website' => $data['website'] ?? null,
            'address' => $data['address'] ?? null,
            'company_code' => $this->codes->generate('companies', 'company_code'),
            'logo_path' => $logo?->store('partner-logos', 'public'),
            'approval_status' => ApprovalStatus::Approved,
            'approved_at' => now(),
        ]);
    }
}

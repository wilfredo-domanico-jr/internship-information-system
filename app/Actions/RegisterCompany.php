<?php

namespace App\Actions;

use App\Enums\AccountStatus;
use App\Enums\ApprovalStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use App\Notifications\CompanyRegistered;
use App\Services\JoinCodeGenerator;
use App\Services\MemberNumberGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class RegisterCompany
{
    public function __construct(
        private readonly MemberNumberGenerator $memberNumbers,
        private readonly JoinCodeGenerator $codes,
    ) {}

    /**
     * @param  array{company_name:string, company_type:string, first_name:string, last_name:string, email:string, phone?:?string, address:string, website?:?string, about?:?string, password:string}  $data
     */
    public function __invoke(array $data, UploadedFile $permit, UploadedFile $moa): User
    {
        $user = DB::transaction(function () use ($data, $permit, $moa) {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'role' => Role::Company,
                'member_no' => $this->memberNumbers->generate(Role::Company),
                'status' => AccountStatus::Active,
            ]);

            $company = Company::create([
                'user_id' => $user->id,
                'name' => $data['company_name'],
                'type' => $data['company_type'],
                'company_code' => $this->codes->generate('companies', 'company_code'),
                'address' => $data['address'],
                'website' => $data['website'] ?? null,
                'about' => $data['about'] ?? null,
                'approval_status' => ApprovalStatus::Pending,
            ]);

            $company->update([
                'permit_path' => $permit->storeAs("companies/{$company->id}", 'permit.pdf', 'local'),
                'moa_path' => $moa->storeAs("companies/{$company->id}", 'moa.pdf', 'local'),
            ]);

            return $user;
        });

        Notification::send(User::ofRole(Role::Admin)->active()->get(), new CompanyRegistered($user->company));

        return $user;
    }
}

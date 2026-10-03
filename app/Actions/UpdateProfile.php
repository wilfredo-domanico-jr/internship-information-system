<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateProfile
{
    /**
     * @param  array<string, mixed>  $data  Validated profile fields.
     */
    public function __invoke(User $user, array $data): void
    {
        DB::transaction(function () use ($user, $data) {
            $user->update([
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
            ]);

            if ($user->isIntern()) {
                $user->internProfile?->update([
                    'gender' => $data['gender'] ?? null,
                    'birthdate' => $data['birthdate'] ?? null,
                    'present_address' => $data['present_address'] ?? null,
                    'permanent_address' => $data['permanent_address'] ?? null,
                    'about' => $data['about'] ?? null,
                ]);
            }

            if ($user->isCompany()) {
                $user->company?->update([
                    'name' => $data['company_name'],
                    'type' => $data['company_type'],
                    'website' => $data['website'] ?? null,
                    'address' => $data['address'],
                    'about' => $data['about'] ?? null,
                ]);
            }
        });
    }
}

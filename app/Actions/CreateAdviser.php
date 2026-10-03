<?php

namespace App\Actions;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\User;
use App\Notifications\AccountCredentials;
use App\Services\MemberNumberGenerator;
use Illuminate\Support\Str;

class CreateAdviser
{
    public function __construct(private readonly MemberNumberGenerator $memberNumbers) {}

    /**
     * @param  array{first_name:string, middle_name?:?string, last_name:string, email:string, phone?:?string}  $data
     */
    public function __invoke(array $data): User
    {
        $password = Str::password(12, symbols: false);

        $user = User::create([
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $password,
            'role' => Role::Adviser,
            'member_no' => $this->memberNumbers->generate(Role::Adviser),
            'status' => AccountStatus::Active,
        ]);

        $user->notify(new AccountCredentials($password));

        return $user;
    }
}

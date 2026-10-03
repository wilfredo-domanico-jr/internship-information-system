<?php

namespace App\Actions;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\ClassSection;
use App\Models\User;
use App\Notifications\InternJoinedClass;
use App\Services\MemberNumberGenerator;
use Illuminate\Support\Facades\DB;

class RegisterIntern
{
    public function __construct(private readonly MemberNumberGenerator $memberNumbers) {}

    /**
     * @param  array{first_name:string, middle_name?:?string, last_name:string, email:string, student_number:string, join_code:string, password:string}  $data
     */
    public function __invoke(array $data): User
    {
        $section = ClassSection::query()->active()->where('join_code', $data['join_code'])->firstOrFail();

        $user = DB::transaction(function () use ($data, $section) {
            $user = User::create([
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => Role::Intern,
                'member_no' => $this->memberNumbers->generate(Role::Intern),
                'status' => AccountStatus::Active,
            ]);

            $user->internProfile()->create([
                'student_number' => $data['student_number'],
                'class_section_id' => $section->id,
                'school_year' => $section->school_year,
            ]);

            return $user;
        });

        $section->adviser?->notify(new InternJoinedClass($user->load('internProfile'), $section));

        return $user;
    }
}

<?php

namespace App\Actions;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\User;
use App\Notifications\AccountCredentials;
use App\Services\MemberNumberGenerator;
use App\Support\ImportColumns;
use App\Support\ImportResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ImportAdvisers
{
    public function __construct(private readonly MemberNumberGenerator $memberNumbers) {}

    /** @param  Collection<int, array<string, mixed>>  $rows */
    public function __invoke(Collection $rows): ImportResult
    {
        $result = new ImportResult;
        $seen = [];
        $prepared = [];

        foreach ($rows as $row) {
            $rowNo = (int) ($row['_row'] ?? 0);
            $email = Str::lower(trim((string) ($row['email'] ?? '')));

            if (ImportColumns::isExampleRow('advisers', $row)) {
                $result->addError($rowNo, "This is the template's example row; delete it before importing.");
            }

            $validator = Validator::make([
                'first_name' => $row['first_name'] ?? null,
                'middle_name' => $row['middle_name'] ?? null,
                'last_name' => $row['last_name'] ?? null,
                'email' => $email ?: null,
                'phone' => $row['phone'] ?? null,
            ], [
                'first_name' => ['required', 'string', 'max:100'],
                'middle_name' => ['nullable', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'phone' => ['nullable', 'string', 'max:30'],
            ], [], ['first_name' => 'first name', 'middle_name' => 'middle name', 'last_name' => 'last name']);

            foreach ($validator->errors()->all() as $message) {
                $result->addError($rowNo, $message);
            }
            if ($email !== '' && isset($seen[$email])) {
                $result->addError($rowNo, "Duplicate email in this file (also on row {$seen[$email]}).");
            }
            $seen[$email] ??= $rowNo;

            $prepared[] = [
                'first_name' => trim((string) ($row['first_name'] ?? '')),
                'middle_name' => filled($row['middle_name'] ?? null) ? trim((string) $row['middle_name']) : null,
                'last_name' => trim((string) ($row['last_name'] ?? '')),
                'email' => $email,
                'phone' => filled($row['phone'] ?? null) ? trim((string) $row['phone']) : null,
            ];
        }

        if ($result->failed()) {
            return $result;
        }

        $credentials = DB::transaction(function () use ($prepared) {
            $created = [];
            foreach ($prepared as $data) {
                $password = Str::password(12, symbols: false);
                $created[] = [User::create([
                    ...$data,
                    'password' => $password,
                    'role' => Role::Adviser,
                    'member_no' => $this->memberNumbers->generate(Role::Adviser),
                    'status' => AccountStatus::Active,
                ]), $password];
            }

            return $created;
        });

        foreach ($credentials as [$user, $password]) {
            $user->notify(new AccountCredentials($password));
        }

        $result->created = count($credentials);

        return $result;
    }
}

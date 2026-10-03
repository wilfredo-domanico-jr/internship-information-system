<?php

namespace App\Actions;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\ClassSection;
use App\Models\InternProfile;
use App\Models\User;
use App\Notifications\AccountCredentials;
use App\Services\MemberNumberGenerator;
use App\Support\ImportColumns;
use App\Support\ImportResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ImportInterns
{
    public function __construct(private readonly MemberNumberGenerator $memberNumbers) {}

    /** @param  Collection<int, array<string, mixed>>  $rows */
    public function __invoke(Collection $rows): ImportResult
    {
        $result = new ImportResult;
        $sections = $this->activeSectionsByName();
        $seenEmails = [];
        $seenStudentNumbers = [];
        $prepared = [];

        foreach ($rows as $row) {
            $rowNo = (int) ($row['_row'] ?? 0);
            $email = Str::lower(trim((string) ($row['email'] ?? '')));
            $studentNumber = trim((string) ($row['student_number'] ?? ''));
            $sectionKey = Str::lower(trim((string) ($row['section'] ?? '')));

            if (ImportColumns::isExampleRow('interns', $row)) {
                $result->addError($rowNo, "This is the template's example row; delete it before importing.");
            }

            $validator = Validator::make([
                'first_name' => $row['first_name'] ?? null,
                'middle_name' => $row['middle_name'] ?? null,
                'last_name' => $row['last_name'] ?? null,
                'email' => $email ?: null,
                'phone' => $row['phone'] ?? null,
                'gender' => $row['gender'] ?? null,
                'student_number' => $studentNumber ?: null,
                'section' => $sectionKey ?: null,
                'school_year' => $row['school_year'] ?? null,
            ], [
                'first_name' => ['required', 'string', 'max:100'],
                'middle_name' => ['nullable', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'phone' => ['nullable', 'string', 'max:30'],
                'gender' => ['nullable', 'string', 'max:20'],
                'student_number' => ['required', 'string', 'max:30', 'unique:intern_profiles,student_number'],
                'section' => ['required', 'string'],
                'school_year' => ['nullable', 'string', 'max:20'],
            ], [], ['first_name' => 'first name', 'middle_name' => 'middle name', 'last_name' => 'last name', 'student_number' => 'student number']);

            foreach ($validator->errors()->all() as $message) {
                $result->addError($rowNo, $message);
            }

            if ($email !== '' && isset($seenEmails[$email])) {
                $result->addError($rowNo, "Duplicate email in this file (also on row {$seenEmails[$email]}).");
            }
            if ($studentNumber !== '' && isset($seenStudentNumbers[$studentNumber])) {
                $result->addError($rowNo, "Duplicate student number in this file (also on row {$seenStudentNumbers[$studentNumber]}).");
            }
            $seenEmails[$email] ??= $rowNo;
            $seenStudentNumbers[$studentNumber] ??= $rowNo;

            $section = null;
            if ($sectionKey !== '') {
                $matches = $sections->get($sectionKey, collect());
                $yearKey = Str::lower(trim((string) ($row['school_year'] ?? '')));
                if ($yearKey !== '') {
                    $matches = $matches->filter(fn (ClassSection $s) => Str::lower(trim($s->school_year)) === $yearKey);
                }
                if ($matches->isEmpty()) {
                    $result->addError($rowNo, "No active class has the section \"{$row['section']}\".");
                } elseif ($matches->count() > 1) {
                    $result->addError($rowNo, "The section \"{$row['section']}\" matches more than one active class; add the school_year column to choose one.");
                } else {
                    $section = $matches->first();
                }
            }

            $prepared[] = [
                'first_name' => trim((string) ($row['first_name'] ?? '')),
                'middle_name' => filled($row['middle_name'] ?? null) ? trim((string) $row['middle_name']) : null,
                'last_name' => trim((string) ($row['last_name'] ?? '')),
                'email' => $email,
                'phone' => filled($row['phone'] ?? null) ? trim((string) $row['phone']) : null,
                'gender' => filled($row['gender'] ?? null) ? trim((string) $row['gender']) : null,
                'student_number' => $studentNumber,
                'school_year' => filled($row['school_year'] ?? null) ? trim((string) $row['school_year']) : $section?->school_year,
                'section' => $section,
            ];
        }

        if ($result->failed()) {
            return $result;
        }

        $credentials = DB::transaction(function () use ($prepared) {
            $created = [];
            foreach ($prepared as $data) {
                $password = Str::password(12, symbols: false);
                $user = User::create([
                    'first_name' => $data['first_name'],
                    'middle_name' => $data['middle_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'password' => $password,
                    'role' => Role::Intern,
                    'member_no' => $this->memberNumbers->generate(Role::Intern),
                    'status' => AccountStatus::Active,
                ]);
                InternProfile::create([
                    'user_id' => $user->id,
                    'student_number' => $data['student_number'],
                    'class_section_id' => $data['section']->id,
                    'school_year' => $data['school_year'],
                    'gender' => $data['gender'],
                ]);
                $created[] = [$user, $password];
            }

            return $created;
        });

        foreach ($credentials as [$user, $password]) {
            $user->notify(new AccountCredentials($password));
        }

        $result->created = count($credentials);

        return $result;
    }

    /** @return Collection<string, Collection<int, ClassSection>> lower-cased section name => matching active classes */
    private function activeSectionsByName(): Collection
    {
        return ClassSection::query()->active()->get(['id', 'section', 'school_year'])
            ->groupBy(fn (ClassSection $s) => Str::lower(trim($s->section)));
    }
}

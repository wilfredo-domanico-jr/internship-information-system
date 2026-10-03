<?php

namespace App\Actions;

use App\Enums\ClassStatus;
use App\Enums\Role;
use App\Models\ClassAdviserLog;
use App\Models\ClassSection;
use App\Models\User;
use App\Services\JoinCodeGenerator;
use App\Support\ImportResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportClasses
{
    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public function __construct(private readonly JoinCodeGenerator $codes) {}

    /** @param  Collection<int, array<string, mixed>>  $rows */
    public function __invoke(Collection $rows): ImportResult
    {
        $result = new ImportResult;
        $existing = ClassSection::query()->active()->get(['course_code', 'section', 'school_year'])
            ->map(fn (ClassSection $c) => $this->identity($c->course_code, $c->section, $c->school_year))->flip();
        $advisers = User::ofRole(Role::Adviser)->active()->get(['id', 'member_no'])->keyBy(fn (User $u) => Str::upper($u->member_no));
        $seen = [];
        $prepared = [];

        foreach ($rows as $row) {
            $rowNo = (int) ($row['_row'] ?? 0);
            $data = [
                'course_code' => Str::upper(trim((string) ($row['course_code'] ?? ''))),
                'subject' => trim((string) ($row['subject'] ?? '')),
                'section' => Str::upper(trim((string) ($row['section'] ?? ''))),
                'school_year' => trim((string) ($row['school_year'] ?? '')),
            ];

            foreach (['course_code' => 'course code', 'subject' => 'subject', 'section' => 'section', 'school_year' => 'school year'] as $key => $label) {
                if ($data[$key] === '') {
                    $result->addError($rowNo, "The {$label} is required.");
                }
            }
            if ($data['school_year'] !== '' && ! preg_match('/^\d{4}-\d{4}$/', $data['school_year'])) {
                $result->addError($rowNo, 'The school year must look like 2025-2026.');
            }

            $day = Str::lower(trim((string) ($row['day'] ?? '')));
            if (! in_array($day, self::DAYS, true)) {
                $result->addError($rowNo, 'The day must be Monday to Sunday.');
            }
            $data['day'] = ucfirst($day);

            $starts = $this->parseTime($row['starts_at'] ?? null);
            $ends = $this->parseTime($row['ends_at'] ?? null);
            if ($starts === null || $ends === null) {
                $result->addError($rowNo, 'Start and end times must be valid times such as 08:00 or 1:00 PM.');
            } elseif ($ends <= $starts) {
                $result->addError($rowNo, 'The end time must be after the start time.');
            }
            $data['starts_at'] = $starts === null ? null : $this->formatTime($starts);
            $data['ends_at'] = $ends === null ? null : $this->formatTime($ends);

            $memberNo = Str::upper(trim((string) ($row['adviser_member_no'] ?? '')));
            $data['adviser_id'] = null;
            if ($memberNo !== '') {
                $adviser = $advisers->get($memberNo);
                if (! $adviser) {
                    $result->addError($rowNo, "No active adviser has the member number {$memberNo}.");
                }
                $data['adviser_id'] = $adviser?->id;
            }

            $identity = $this->identity($data['course_code'], $data['section'], $data['school_year']);
            if ($existing->has($identity)) {
                $result->addError($rowNo, "An active class {$data['course_code']} {$data['section']} ({$data['school_year']}) already exists.");
            } elseif (isset($seen[$identity])) {
                $result->addError($rowNo, "Duplicate class in this file (also on row {$seen[$identity]}).");
            }
            $seen[$identity] ??= $rowNo;

            $prepared[] = $data;
        }

        if ($result->failed()) {
            return $result;
        }

        DB::transaction(function () use ($prepared) {
            foreach ($prepared as $data) {
                $section = ClassSection::create([
                    ...$data,
                    'join_code' => $this->codes->generate('class_sections', 'join_code'),
                    'status' => ClassStatus::Active,
                ]);
                if ($data['adviser_id']) {
                    ClassAdviserLog::create(['class_section_id' => $section->id, 'adviser_id' => $data['adviser_id'], 'joined_at' => now()]);
                }
            }
        });

        $result->created = count($prepared);

        return $result;
    }

    private function identity(string $courseCode, string $section, string $schoolYear): string
    {
        return Str::lower("{$courseCode}|{$section}|{$schoolYear}");
    }

    /** Accepts H:MM, HH:MM, H:MM:SS, optionally followed by AM/PM. Returns minutes since midnight, or null. */
    private function parseTime(mixed $value): ?int
    {
        $value = strtoupper(trim((string) $value));
        if (! preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(AM|PM)?$/', $value, $m)) {
            return null;
        }
        [$hour, $minute, $second] = [(int) $m[1], (int) $m[2], (int) ($m[3] ?? 0)];
        $meridiem = $m[4] ?? null;
        if ($minute > 59 || $second > 59) {
            return null;
        }
        if ($meridiem) {
            if ($hour < 1 || $hour > 12) {
                return null;
            }
            $hour = $hour % 12 + ($meridiem === 'PM' ? 12 : 0);
        } elseif ($hour > 23) {
            return null;
        }

        return $hour * 60 + $minute;
    }

    private function formatTime(int $minutes): string
    {
        return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
    }
}

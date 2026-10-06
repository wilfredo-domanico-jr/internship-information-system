<?php

namespace App\Actions;

use App\Enums\ClassStatus;
use App\Models\ClassAdviserLog;
use App\Models\ClassSection;
use App\Models\User;
use App\Services\JoinCodeGenerator;
use Illuminate\Support\Facades\DB;

class CreateClassSection
{
    public function __construct(private readonly JoinCodeGenerator $codes) {}

    /** @param  array{course_code:string, subject:string, section:string, day:string, starts_at:string, ends_at:string, school_year:string}  $data */
    public function __invoke(array $data, User $adviser): ClassSection
    {
        return DB::transaction(function () use ($data, $adviser) {
            $section = ClassSection::create([
                'adviser_id' => $adviser->id,
                'course_code' => $data['course_code'],
                'subject' => $data['subject'],
                'section' => $data['section'],
                'day' => $data['day'],
                'starts_at' => self::dbTime($data['starts_at']),
                'ends_at' => self::dbTime($data['ends_at']),
                'school_year' => $data['school_year'],
                'join_code' => $this->codes->generate('class_sections', 'join_code'),
                'status' => ClassStatus::Active,
            ]);

            ClassAdviserLog::create(['class_section_id' => $section->id, 'adviser_id' => $adviser->id, 'joined_at' => now()]);

            return $section;
        });
    }

    /** "08:00" → "08:00:00" so stored times match the Excel import format. */
    public static function dbTime(string $time): string
    {
        return strlen($time) === 5 ? "{$time}:00" : $time;
    }
}

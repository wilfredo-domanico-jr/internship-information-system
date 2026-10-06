<?php

namespace App\Actions;

use App\Models\ClassSection;

class UpdateClassSection
{
    /** @param  array{course_code:string, subject:string, section:string, day:string, starts_at:string, ends_at:string, school_year:string}  $data */
    public function __invoke(ClassSection $section, array $data): ClassSection
    {
        $section->update([
            'course_code' => $data['course_code'],
            'subject' => $data['subject'],
            'section' => $data['section'],
            'day' => $data['day'],
            'starts_at' => CreateClassSection::dbTime($data['starts_at']),
            'ends_at' => CreateClassSection::dbTime($data['ends_at']),
            'school_year' => $data['school_year'],
        ]);

        return $section;
    }
}

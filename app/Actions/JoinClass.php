<?php

namespace App\Actions;

use App\Enums\ClassStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\ClassSection;
use App\Models\User;
use App\Notifications\InternJoinedClass;
use Illuminate\Support\Str;

/** An intern enrols in a class by join code. One active class per intern. */
class JoinClass
{
    public function __invoke(User $intern, string $joinCode): ClassSection
    {
        $profile = $intern->internProfile;

        if (! $profile) {
            throw new DomainRuleViolation('Only interns can join a class.');
        }

        $current = $profile->classSection;

        if ($current && $current->status === ClassStatus::Active) {
            throw new DomainRuleViolation("You are already enrolled in {$current->display_name}.");
        }

        $section = ClassSection::query()->active()->where('join_code', Str::upper(trim($joinCode)))->first();

        if (! $section) {
            throw new DomainRuleViolation('No active class has that join code.');
        }

        $profile->update(['class_section_id' => $section->id, 'school_year' => $section->school_year]);

        $section->adviser?->notify(new InternJoinedClass($intern->load('internProfile'), $section));

        return $section;
    }
}

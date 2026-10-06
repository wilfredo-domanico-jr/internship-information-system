<?php

namespace App\Actions;

use App\Exceptions\DomainRuleViolation;
use App\Models\ClassAdviserLog;
use App\Models\ClassSection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** An adviser takes over an imported or vacated class by entering its join code. */
class ClaimClass
{
    public function __invoke(string $joinCode, User $adviser): ClassSection
    {
        $section = ClassSection::query()->active()->where('join_code', Str::upper(trim($joinCode)))->first();

        if (! $section) {
            throw new DomainRuleViolation('No active class has that join code.');
        }

        if ($section->isAdvisedBy($adviser)) {
            throw new DomainRuleViolation("You already advise {$section->display_name}.");
        }

        if ($section->adviser_id !== null) {
            throw new DomainRuleViolation("{$section->display_name} already has an adviser.");
        }

        return DB::transaction(function () use ($section, $adviser) {
            $section->update(['adviser_id' => $adviser->id]);
            ClassAdviserLog::create(['class_section_id' => $section->id, 'adviser_id' => $adviser->id, 'joined_at' => now()]);

            return $section;
        });
    }
}

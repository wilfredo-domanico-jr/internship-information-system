<?php

namespace App\Actions;

use App\Exceptions\DomainRuleViolation;
use App\Models\ClassAdviserLog;
use App\Models\ClassSection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** The adviser steps down; interns stay enrolled and the class becomes claimable again. */
class LeaveClass
{
    public function __invoke(ClassSection $section, User $adviser): void
    {
        if (! $section->isAdvisedBy($adviser)) {
            throw new DomainRuleViolation('You do not advise this class.');
        }

        DB::transaction(function () use ($section, $adviser) {
            $section->update(['adviser_id' => null]);

            $open = ClassAdviserLog::query()
                ->where('class_section_id', $section->id)->where('adviser_id', $adviser->id)->whereNull('left_at')
                ->latest('joined_at')->first();

            $open
                ? $open->update(['left_at' => now()])
                : ClassAdviserLog::create(['class_section_id' => $section->id, 'adviser_id' => $adviser->id, 'joined_at' => $section->created_at, 'left_at' => now()]);
        });
    }
}

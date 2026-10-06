<?php

namespace App\Actions;

use App\Enums\DtrStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Dtr;
use App\Models\User;
use App\Notifications\DtrSubmitted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/** A DTR is always pending on submission; only ApproveDtr turns it into hours. */
class SubmitDtr
{
    /** @param  array{period_from:string, period_to:string, hours:int|string, absences:int|string}  $data */
    public function __invoke(User $intern, array $data, UploadedFile $file): Dtr
    {
        $placement = $intern->activePlacement()->with('company.user')->first();

        if (! $placement) {
            throw new DomainRuleViolation('You are not placed with a company, so there is nowhere to send a DTR.');
        }

        $path = $file->storeAs("dtrs/{$placement->id}", Str::uuid().'.pdf', 'local');

        $dtr = $placement->dtrs()->create([
            'file_path' => $path,
            'period_from' => $data['period_from'],
            'period_to' => $data['period_to'],
            'hours' => (int) $data['hours'],
            'absences' => (int) $data['absences'],
            'status' => DtrStatus::Pending,
        ]);

        $placement->company->user?->notify(new DtrSubmitted($dtr->setRelation('placement', $placement->setRelation('intern', $intern))));

        return $dtr;
    }
}

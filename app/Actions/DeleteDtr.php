<?php

namespace App\Actions;

use App\Enums\DtrStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Dtr;
use Illuminate\Support\Facades\Storage;

class DeleteDtr
{
    public function __invoke(Dtr $dtr): void
    {
        if ($dtr->status !== DtrStatus::Pending) {
            throw new DomainRuleViolation('Only pending DTRs can be withdrawn.');
        }

        $path = $dtr->file_path;

        $dtr->delete();

        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }
}

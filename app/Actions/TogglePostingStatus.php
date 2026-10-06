<?php

namespace App\Actions;

use App\Enums\PostingStatus;
use App\Models\InternshipPosting;

class TogglePostingStatus
{
    public function __invoke(InternshipPosting $posting): InternshipPosting
    {
        $posting->update(['status' => $posting->status === PostingStatus::Open ? PostingStatus::Closed : PostingStatus::Open]);

        return $posting;
    }
}

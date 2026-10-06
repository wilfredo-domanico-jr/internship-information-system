<?php

namespace App\Actions;

use App\Models\InternshipPosting;

class UpdatePosting
{
    /** @param  array<string, mixed>  $data  Same keys as CreatePosting. */
    public function __invoke(InternshipPosting $posting, array $data): InternshipPosting
    {
        $posting->update(CreatePosting::attributes($data));

        return $posting;
    }
}

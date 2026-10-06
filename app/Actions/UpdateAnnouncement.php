<?php

namespace App\Actions;

use App\Models\Announcement;
use App\Services\HtmlSanitizer;

class UpdateAnnouncement
{
    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    public function __invoke(Announcement $announcement, string $body): Announcement
    {
        $announcement->update(['body' => $this->sanitizer->clean($body)]);

        return $announcement;
    }
}

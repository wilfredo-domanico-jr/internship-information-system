<?php

namespace App\Actions;

use App\Enums\AccountStatus;
use App\Models\Announcement;
use App\Models\ClassSection;
use App\Models\User;
use App\Notifications\AnnouncementPosted;
use App\Services\HtmlSanitizer;
use Illuminate\Support\Facades\Notification;

class PostAnnouncement
{
    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    public function __invoke(ClassSection $section, User $author, string $body): Announcement
    {
        $announcement = $section->announcements()->create([
            'author_id' => $author->id,
            'body' => $this->sanitizer->clean($body),
        ]);

        $interns = $section->interns()->where('users.status', AccountStatus::Active)->get();

        Notification::send($interns, new AnnouncementPosted($announcement));

        return $announcement;
    }
}

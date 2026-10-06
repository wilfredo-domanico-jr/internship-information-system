<?php

namespace App\Actions;

use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Models\User;
use App\Notifications\AnnouncementCommented;

class AddComment
{
    public function __invoke(Announcement $announcement, User $author, string $body): AnnouncementComment
    {
        $comment = $announcement->comments()->create(['author_id' => $author->id, 'body' => trim($body)]);

        $adviser = $announcement->classSection->adviser;

        if ($adviser && ! $author->is($adviser)) {
            $adviser->notify(new AnnouncementCommented($comment->load('author')));
        }

        return $comment;
    }
}

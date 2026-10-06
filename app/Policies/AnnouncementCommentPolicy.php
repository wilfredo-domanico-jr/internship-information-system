<?php

namespace App\Policies;

use App\Models\AnnouncementComment;
use App\Models\User;

class AnnouncementCommentPolicy
{
    /** The comment's author, or the adviser moderating their class. */
    public function delete(User $user, AnnouncementComment $comment): bool
    {
        return $comment->author_id === $user->id || $comment->announcement->classSection->isAdvisedBy($user);
    }
}

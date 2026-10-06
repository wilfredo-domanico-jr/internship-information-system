<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    public function view(User $user, Announcement $announcement): bool
    {
        return $user->isAdmin() || $announcement->classSection->hasMember($user);
    }

    public function comment(User $user, Announcement $announcement): bool
    {
        return $announcement->classSection->hasMember($user);
    }

    /** The author, and only while they still advise the class. */
    public function update(User $user, Announcement $announcement): bool
    {
        return $announcement->author_id === $user->id && $announcement->classSection->isAdvisedBy($user);
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $this->update($user, $announcement);
    }
}

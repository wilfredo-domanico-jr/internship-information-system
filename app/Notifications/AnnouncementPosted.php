<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class AnnouncementPosted extends Notification
{
    use Queueable;

    public function __construct(public Announcement $announcement) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $section = $this->announcement->classSection;

        return [
            'title' => "New announcement in {$section->display_name}",
            'body' => Str::limit(trim(strip_tags($this->announcement->body)), 120),
            'url' => route('intern.class.show'),
            'icon' => 'heroicon-o-megaphone',
        ];
    }
}

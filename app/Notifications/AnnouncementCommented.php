<?php

namespace App\Notifications;

use App\Models\AnnouncementComment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class AnnouncementCommented extends Notification
{
    use Queueable;

    public function __construct(public AnnouncementComment $comment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $section = $this->comment->announcement->classSection;

        return [
            'title' => "New comment in {$section->display_name}",
            'body' => "{$this->comment->author->name}: ".Str::limit($this->comment->body, 100),
            'url' => $notifiable instanceof User && $notifiable->isAdviser()
                ? route('adviser.classes.show', $section)
                : route('intern.class.show'),
            'icon' => 'heroicon-o-chat-bubble-left',
        ];
    }
}

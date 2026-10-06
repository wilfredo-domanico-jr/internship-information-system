<?php

namespace App\Notifications;

use App\Models\ClassSection;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InternJoinedClass extends Notification
{
    use Queueable;

    public function __construct(public User $intern, public ClassSection $section) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "New intern in {$this->section->display_name}",
            'body' => "{$this->intern->name} ({$this->intern->internProfile?->student_number}) joined your class.",
            'url' => route('adviser.classes.people', $this->section),
            'icon' => 'heroicon-o-academic-cap',
        ];
    }
}

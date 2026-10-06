<?php

namespace App\Notifications;

use App\Models\Placement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InternRemoved extends Notification
{
    use Queueable;

    public function __construct(public Placement $placement) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "Your placement at {$this->placement->company->name} has ended",
            'body' => "The company ended your placement with {$this->placement->hours_rendered} hours rendered. Those hours stay in your total.",
            'url' => route('intern.internship.show'),
            'icon' => 'heroicon-o-arrow-right-start-on-rectangle',
        ];
    }
}

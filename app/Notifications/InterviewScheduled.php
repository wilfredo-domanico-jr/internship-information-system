<?php

namespace App\Notifications;

use App\Models\Interview;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class InterviewScheduled extends Notification
{
    use Queueable;

    public function __construct(public Interview $interview) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $posting = $this->interview->application->posting;
        $when = $this->interview->scheduled_on->format('M j, Y').' at '.Carbon::parse($this->interview->starts_at)->format('g:i A');

        return [
            'title' => "Interview scheduled with {$posting->company->name}",
            'body' => "{$this->interview->title} for {$posting->title}: {$when} · {$this->interview->venue}.",
            'url' => route('intern.applications.index'),
            'icon' => 'heroicon-o-calendar-days',
        ];
    }
}

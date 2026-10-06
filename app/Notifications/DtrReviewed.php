<?php

namespace App\Notifications;

use App\Enums\DtrStatus;
use App\Models\Dtr;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DtrReviewed extends Notification
{
    use Queueable;

    public function __construct(public Dtr $dtr) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $approved = $this->dtr->status === DtrStatus::Approved;
        $period = "{$this->dtr->period_from->format('M j')} – {$this->dtr->period_to->format('M j')}";

        return [
            'title' => $approved ? "DTR approved: {$this->dtr->hours} hours credited" : "DTR disapproved for {$period}",
            'body' => $approved
                ? "Your DTR for {$period} was approved. {$this->dtr->hours} hours were added to your OJT total."
                : ($this->dtr->reviewer_note ?? 'Please resubmit a corrected DTR.'),
            'url' => route('intern.dtrs.index'),
            'icon' => $approved ? 'heroicon-o-check-badge' : 'heroicon-o-x-circle',
        ];
    }
}

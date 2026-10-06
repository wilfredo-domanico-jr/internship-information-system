<?php

namespace App\Notifications;

use App\Models\Dtr;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class DtrSubmitted extends Notification
{
    use Queueable;

    public function __construct(public Dtr $dtr) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "{$this->dtr->placement->intern->name} submitted a DTR",
            'body' => "{$this->dtr->hours} hours for {$this->dtr->period_from->format('M j')} – {$this->dtr->period_to->format('M j, Y')}. Review it to credit the hours.",
            'url' => Route::has('company.dtrs.index') ? route('company.dtrs.index') : route('company.dashboard'),
            'icon' => 'heroicon-o-clipboard-document-check',
        ];
    }
}

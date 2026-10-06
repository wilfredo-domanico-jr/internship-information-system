<?php

namespace App\Notifications;

use App\Models\Placement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class InternLeftCompany extends Notification
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
            'title' => "{$this->placement->intern->name} left your company",
            'body' => "Their placement ended with {$this->placement->hours_rendered} hours rendered.",
            'url' => Route::has('company.history.index') ? route('company.history.index') : route('company.dashboard'),
            'icon' => 'heroicon-o-arrow-right-start-on-rectangle',
        ];
    }
}

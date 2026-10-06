<?php

namespace App\Notifications;

use App\Models\Placement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class InternJoinedCompany extends Notification
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
            'title' => "{$this->placement->intern->name} joined your company",
            'body' => 'They entered your company code and are now an active intern. Assign them a department from Interns.',
            'url' => Route::has('company.interns.index') ? route('company.interns.index') : route('company.dashboard'),
            'icon' => 'heroicon-o-user-plus',
        ];
    }
}

<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CompanyRegistered extends Notification
{
    use Queueable;

    public function __construct(public Company $company) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New company registration',
            'body' => "{$this->company->name} submitted a business permit and MOA for verification.",
            'url' => route('admin.dashboard'),
            'icon' => 'heroicon-o-building-office-2',
        ];
    }
}

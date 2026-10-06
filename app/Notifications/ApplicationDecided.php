<?php

namespace App\Notifications;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class ApplicationDecided extends Notification
{
    use Queueable;

    public function __construct(public Application $application) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $posting = $this->application->posting;
        $company = $posting->company;
        $accepted = $this->application->status === ApplicationStatus::Accepted;

        return [
            'title' => $accepted ? "You were accepted by {$company->name}" : "{$company->name} declined your application",
            'body' => $accepted
                ? "Congratulations! Join {$company->name} with the company code {$company->company_code} on My internship."
                : "{$posting->title}: ".($this->application->decline_reason ?? 'No reason given.'),
            'url' => $accepted && Route::has('intern.internship.show') ? route('intern.internship.show') : route('intern.applications.index'),
            'icon' => $accepted ? 'heroicon-o-check-badge' : 'heroicon-o-x-circle',
        ];
    }
}

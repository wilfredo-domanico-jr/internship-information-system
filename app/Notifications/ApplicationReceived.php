<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class ApplicationReceived extends Notification
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

        return [
            'title' => "New applicant for {$posting->title}",
            'body' => "{$this->application->intern->name} applied with a resume and endorsement letter.",
            'url' => Route::has('company.postings.applicants') ? route('company.postings.applicants', $posting) : route('company.postings.index'),
            'icon' => 'heroicon-o-user-plus',
        ];
    }
}

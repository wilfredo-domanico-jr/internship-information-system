<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompanyApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Company $company)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Your company is verified',
            'body' => "{$this->company->name} has been approved by the ".config('wiis.institution.office').'. You can now post internships and accept interns.',
            'url' => route('company.dashboard'),
            'icon' => 'heroicon-o-check-badge',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->company->name.' is verified on '.config('wiis.name'))
            ->greeting("Hello {$notifiable->first_name},")
            ->line($this->toArray($notifiable)['body'])
            ->action('Open your dashboard', route('company.dashboard'));
    }
}

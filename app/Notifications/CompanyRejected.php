<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompanyRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Company $company, public ?string $reason = null)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        $reason = $this->reason ? " Reason: {$this->reason}" : ' Please contact '.config('wiis.support.email').' for details.';

        return [
            'title' => 'Company verification not approved',
            'body' => "The registration for {$this->company->name} was not approved.".$reason,
            'url' => route('account.pending'),
            'icon' => 'heroicon-o-x-circle',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Update on your '.config('wiis.name').' registration')
            ->greeting("Hello {$notifiable->first_name},")
            ->line($this->toArray($notifiable)['body'])
            ->line('You may reply to the placement office with corrected documents.');
    }
}

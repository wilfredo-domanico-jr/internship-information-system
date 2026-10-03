<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountCredentials extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $plainPassword) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your '.config('wiis.name').' account')
            ->greeting("Hello {$notifiable->first_name},")
            ->line('An account has been created for you on '.config('wiis.name').' by the '.config('wiis.institution.office').'.')
            ->line("Email: {$notifiable->email}")
            ->line("Temporary password: {$this->plainPassword}")
            ->action('Sign in', route('login'))
            ->line('Please change your password after your first sign-in.');
    }
}

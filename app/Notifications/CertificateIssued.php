<?php

namespace App\Notifications;

use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CertificateIssued extends Notification
{
    use Queueable;

    public function __construct(public Certificate $certificate) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "{$this->certificate->placement->company->name} issued your certificate",
            'body' => "Certificate for {$this->certificate->hours_at_issue} hours rendered. Download it from Certificates.",
            'url' => route('intern.certificates.index'),
            'icon' => 'heroicon-o-trophy',
        ];
    }
}

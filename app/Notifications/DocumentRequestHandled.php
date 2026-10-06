<?php

namespace App\Notifications;

use App\Enums\DocumentRequestStatus;
use App\Models\DocumentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DocumentRequestHandled extends Notification
{
    use Queueable;

    public function __construct(public DocumentRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $fulfilled = $this->request->status === DocumentRequestStatus::Fulfilled;
        $company = $this->request->placement->company->name;

        return [
            'title' => $fulfilled ? "Your {$this->request->document_name} is ready" : "{$company} declined your request for {$this->request->document_name}",
            'body' => $fulfilled ? "{$company} uploaded the document for request {$this->request->control_no}. Download it from Requests." : "Request {$this->request->control_no} was declined. Ask your company contact if you need more details.",
            'url' => route('intern.requests.index'),
            'icon' => $fulfilled ? 'heroicon-o-document-check' : 'heroicon-o-x-circle',
        ];
    }
}

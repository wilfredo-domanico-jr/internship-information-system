<?php

namespace App\Notifications;

use App\Models\DocumentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class DocumentRequested extends Notification
{
    use Queueable;

    public function __construct(public DocumentRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "{$this->request->placement->intern->name} requested a document",
            'body' => "{$this->request->control_no}: {$this->request->document_name}".($this->request->message ? " — {$this->request->message}" : '.'),
            'url' => Route::has('company.requests.index') ? route('company.requests.index') : route('company.dashboard'),
            'icon' => 'heroicon-o-document-text',
        ];
    }
}

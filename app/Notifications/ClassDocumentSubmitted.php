<?php

namespace App\Notifications;

use App\Models\ClassSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ClassDocumentSubmitted extends Notification
{
    use Queueable;

    public function __construct(public ClassSubmission $submission) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $folder = $this->submission->folder;

        return [
            'title' => "New submission in {$folder->name}",
            'body' => "{$this->submission->intern->name} uploaded “{$this->submission->title}”".($this->submission->is_late ? ' (late).' : '.'),
            'url' => route('adviser.folders.show', $folder),
            'icon' => 'heroicon-o-document-arrow-up',
        ];
    }
}

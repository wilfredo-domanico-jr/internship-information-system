<?php

namespace App\Notifications;

use App\Enums\SubmissionStatus;
use App\Models\ClassSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ClassSubmissionReviewed extends Notification
{
    use Queueable;

    public function __construct(public ClassSubmission $submission) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $approved = $this->submission->status === SubmissionStatus::Approved;
        $folder = $this->submission->folder;

        return [
            'title' => "“{$this->submission->title}” was ".($approved ? 'approved' : 'declined'),
            'body' => $this->submission->reviewer_note ?? "Your upload in {$folder->name} was ".($approved ? 'approved.' : 'declined.'),
            'url' => route('intern.folders.show', $folder),
            'icon' => $approved ? 'heroicon-o-check-badge' : 'heroicon-o-x-circle',
        ];
    }
}

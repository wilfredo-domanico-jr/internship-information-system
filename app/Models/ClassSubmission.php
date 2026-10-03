<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Database\Factories\ClassSubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassSubmission extends Model
{
    /** @use HasFactory<ClassSubmissionFactory> */
    use HasFactory;

    protected $fillable = [
        'class_folder_id', 'intern_id', 'title', 'file_path', 'status', 'is_late',
        'reviewer_note', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'is_late' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(ClassFolder::class, 'class_folder_id');
    }

    public function intern(): BelongsTo
    {
        return $this->belongsTo(User::class, 'intern_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

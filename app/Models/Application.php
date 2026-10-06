<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory;

    protected $fillable = [
        'internship_posting_id', 'intern_id', 'resume_path', 'endorsement_path',
        'status', 'decline_reason', 'decided_at',
    ];

    protected function casts(): array
    {
        return ['status' => ApplicationStatus::class, 'decided_at' => 'datetime'];
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->intern_id === $user->id;
    }

    public function isManagedBy(User $user): bool
    {
        return $this->posting->company->isManagedBy($user);
    }

    public function posting(): BelongsTo
    {
        return $this->belongsTo(InternshipPosting::class, 'internship_posting_id');
    }

    public function intern(): BelongsTo
    {
        return $this->belongsTo(User::class, 'intern_id');
    }

    public function interview(): HasOne
    {
        return $this->hasOne(Interview::class);
    }
}

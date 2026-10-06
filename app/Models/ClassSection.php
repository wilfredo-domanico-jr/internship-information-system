<?php

namespace App\Models;

use App\Enums\ClassStatus;
use Database\Factories\ClassSectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

class ClassSection extends Model
{
    /** @use HasFactory<ClassSectionFactory> */
    use HasFactory;

    protected $fillable = [
        'adviser_id', 'course_code', 'subject', 'section', 'day', 'starts_at', 'ends_at',
        'school_year', 'join_code', 'status',
    ];

    protected function casts(): array
    {
        return ['status' => ClassStatus::class];
    }

    protected function scheduleLabel(): Attribute
    {
        return Attribute::get(function () {
            $from = Carbon::parse($this->starts_at)->format('g:i A');
            $to = Carbon::parse($this->ends_at)->format('g:i A');

            return "{$this->day} {$from} – {$to}";
        });
    }

    protected function displayName(): Attribute
    {
        return Attribute::get(fn () => "{$this->course_code} · {$this->section}");
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ClassStatus::Active);
    }

    /* Membership */

    public function isAdvisedBy(User $user): bool
    {
        return $this->adviser_id !== null && $this->adviser_id === $user->id;
    }

    public function enrolls(User $user): bool
    {
        return $user->isIntern() && $user->internProfile?->class_section_id === $this->id;
    }

    public function hasMember(User $user): bool
    {
        return $this->isAdvisedBy($user) || $this->enrolls($user);
    }

    public function adviser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adviser_id');
    }

    public function internProfiles(): HasMany
    {
        return $this->hasMany(InternProfile::class);
    }

    public function interns(): HasManyThrough
    {
        return $this->hasManyThrough(User::class, InternProfile::class, 'class_section_id', 'id', 'id', 'user_id');
    }

    public function submissions(): HasManyThrough
    {
        return $this->hasManyThrough(ClassSubmission::class, ClassFolder::class);
    }

    public function folders(): HasMany
    {
        return $this->hasMany(ClassFolder::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class)->latest();
    }

    public function resources(): HasMany
    {
        return $this->hasMany(ClassResource::class);
    }

    public function adviserLogs(): HasMany
    {
        return $this->hasMany(ClassAdviserLog::class);
    }
}

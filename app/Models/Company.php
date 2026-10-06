<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'type', 'company_code', 'logo_path', 'about', 'website', 'address',
        'permit_path', 'moa_path', 'approval_status', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'approval_status' => ApprovalStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function isPartner(): bool
    {
        return $this->user_id === null;
    }

    public function isRegistered(): bool
    {
        return $this->user_id !== null;
    }

    public function isApproved(): bool
    {
        return $this->approval_status === ApprovalStatus::Approved;
    }

    /** True for the registered company's own login; partner companies have no login. */
    public function isManagedBy(User $user): bool
    {
        return $this->user_id !== null && $this->user_id === $user->id;
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approval_status', ApprovalStatus::Approved);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('approval_status', ApprovalStatus::Pending);
    }

    public function scopePartners(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }

    public function scopeRegistered(Builder $query): Builder
    {
        return $query->whereNotNull('user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function postings(): HasMany
    {
        return $this->hasMany(InternshipPosting::class)->latest();
    }

    public function cosApplications(): HasMany
    {
        return $this->hasMany(CosApplication::class);
    }

    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class);
    }

    public function activePlacements(): HasMany
    {
        return $this->hasMany(Placement::class)->whereNull('ended_at');
    }
}

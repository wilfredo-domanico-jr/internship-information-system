<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\PostingStatus;
use Database\Factories\InternshipPostingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InternshipPosting extends Model
{
    /** @use HasFactory<InternshipPostingFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id', 'title', 'city', 'description', 'responsibilities', 'closing_date',
        'required_hours', 'vacancies', 'contact_name', 'contact_position', 'contact_phone', 'status',
    ];

    protected function casts(): array
    {
        return ['status' => PostingStatus::class, 'closing_date' => 'date'];
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', PostingStatus::Open);
    }

    /** Its company is approved and its login is active. */
    public function companyIsOpenForBusiness(): bool
    {
        return $this->company->isApproved() && $this->company->user?->status === AccountStatus::Active;
    }

    /** Open and either without a closing date or closing today or later. */
    public function isAcceptingApplications(): bool
    {
        return $this->status === PostingStatus::Open
            && $this->companyIsOpenForBusiness()
            && ($this->closing_date === null || $this->closing_date->greaterThanOrEqualTo(today()));
    }

    public function scopeAccepting(Builder $query): Builder
    {
        return $query->open()
            ->where(fn (Builder $q) => $q->whereNull('closing_date')->orWhereDate('closing_date', '>=', today()))
            ->whereHas('company', fn (Builder $c) => $c->approved()
                ->whereHas('user', fn (Builder $u) => $u->where('status', AccountStatus::Active)));
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}

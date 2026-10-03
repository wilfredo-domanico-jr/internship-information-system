<?php

namespace App\Models;

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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}

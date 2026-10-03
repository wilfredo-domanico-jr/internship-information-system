<?php

namespace App\Models;

use App\Enums\CosApplicationStatus;
use Database\Factories\CosApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CosApplication extends Model
{
    /** @use HasFactory<CosApplicationFactory> */
    use HasFactory;

    protected $fillable = ['company_id', 'intern_id', 'acceptance_letter_path', 'status', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['status' => CosApplicationStatus::class, 'reviewed_at' => 'datetime'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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

<?php

namespace App\Models;

use App\Enums\DtrStatus;
use Database\Factories\DtrFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dtr extends Model
{
    /** @use HasFactory<DtrFactory> */
    use HasFactory;

    protected $fillable = [
        'placement_id', 'file_path', 'period_from', 'period_to', 'hours', 'absences',
        'status', 'reviewer_id', 'reviewer_note', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DtrStatus::class,
            'period_from' => 'date',
            'period_to' => 'date',
            'hours' => 'integer',
            'absences' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(Placement::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}

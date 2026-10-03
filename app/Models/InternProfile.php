<?php

namespace App\Models;

use Database\Factories\InternProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternProfile extends Model
{
    /** @use HasFactory<InternProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'student_number', 'gender', 'birthdate', 'present_address', 'permanent_address',
        'about', 'school_year', 'resume_path', 'class_section_id', 'total_hours', 'total_absences',
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'total_hours' => 'integer',
            'total_absences' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function classSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class);
    }
}

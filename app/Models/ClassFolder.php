<?php

namespace App\Models;

use Database\Factories\ClassFolderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassFolder extends Model
{
    /** @use HasFactory<ClassFolderFactory> */
    use HasFactory;

    protected $fillable = ['class_section_id', 'name', 'is_locked'];

    protected function casts(): array
    {
        return ['is_locked' => 'boolean'];
    }

    public function classSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ClassSubmission::class);
    }
}

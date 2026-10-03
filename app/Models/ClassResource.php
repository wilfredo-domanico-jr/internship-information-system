<?php

namespace App\Models;

use Database\Factories\ClassResourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassResource extends Model
{
    /** @use HasFactory<ClassResourceFactory> */
    use HasFactory;

    protected $fillable = ['class_section_id', 'uploader_id', 'title', 'file_path'];

    public function classSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }
}

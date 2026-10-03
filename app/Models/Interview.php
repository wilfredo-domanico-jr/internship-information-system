<?php

namespace App\Models;

use Database\Factories\InterviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Interview extends Model
{
    /** @use HasFactory<InterviewFactory> */
    use HasFactory;

    protected $fillable = ['application_id', 'title', 'venue', 'link', 'scheduled_on', 'starts_at', 'ends_at', 'notes'];

    protected function casts(): array
    {
        return ['scheduled_on' => 'date'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}

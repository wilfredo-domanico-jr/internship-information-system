<?php

namespace App\Models;

use Database\Factories\AnnouncementCommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementComment extends Model
{
    /** @use HasFactory<AnnouncementCommentFactory> */
    use HasFactory;

    protected $fillable = ['announcement_id', 'author_id', 'body'];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}

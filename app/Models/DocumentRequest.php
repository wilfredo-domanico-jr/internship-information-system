<?php

namespace App\Models;

use App\Enums\DocumentRequestStatus;
use Database\Factories\DocumentRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRequest extends Model
{
    /** @use HasFactory<DocumentRequestFactory> */
    use HasFactory;

    protected $fillable = ['placement_id', 'control_no', 'document_name', 'message', 'status', 'file_path', 'handled_at'];

    protected function casts(): array
    {
        return ['status' => DocumentRequestStatus::class, 'handled_at' => 'datetime'];
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(Placement::class);
    }
}

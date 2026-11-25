<?php

namespace App\Models;

use App\Enums\TicketLogType;
use App\Services\TicketLogger;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'attachable_type',
    'attachable_id',
    'file_path',
    'file_name',
    'file_size',
    'mime_type',
    'uploaded_by',
])]
class Attachment extends Model
{
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    protected static function booted(): void
    {
        // Ticket files only; approval and message files are part of their own logs.
        static::created(fn (Attachment $a) => $a->attachable_type === Ticket::class && TicketLogger::log($a->attachable, TicketLogType::AttachmentAdded, meta: ['file_name' => $a->file_name, 'file_size' => $a->file_size]));
        static::deleted(fn (Attachment $a) => $a->attachable_type === Ticket::class && $a->attachable && TicketLogger::log($a->attachable, TicketLogType::AttachmentRemoved, meta: ['file_name' => $a->file_name]));
    }
}

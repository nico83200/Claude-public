<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantAction extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['undo' => 'array', 'undone_at' => 'datetime'];

    /** Fenêtre d'annulation depuis l'historique. */
    public const UNDO_HOURS = 24;

    public function request()
    {
        return $this->belongsTo(AssistantRequest::class, 'assistant_request_id');
    }

    public function subject()
    {
        return $this->morphTo();
    }

    public function isUndoable(): bool
    {
        return ! $this->undone_at && ! empty($this->undo) && $this->created_at->gt(now()->subHours(self::UNDO_HOURS));
    }
}

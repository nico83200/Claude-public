<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AssistantRequest extends Model
{
    protected $guarded = ['id', 'paid_with_credit'];

    protected $casts = ['paid_with_credit' => 'boolean'];

    protected static function booted(): void
    {
        static::creating(fn (self $r) => $r->uuid ??= (string) Str::uuid());
        // Au-delà du quota mensuel, la demande utilise un crédit IA acheté (rendu si elle échoue).
        static::created(fn (self $r) => app(\App\Services\Assistant\AssistantCredits::class)->charge($r));
        static::updated(function (self $r) {
            if ($r->wasChanged('status')) {
                app(\App\Services\Assistant\AssistantCredits::class)->refund($r);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function actions()
    {
        return $this->hasMany(AssistantAction::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Identité Google ou Apple liée à un compte (identifiant stable « sub » du fournisseur). */
class SocialAccount extends Model
{
    public const PROVIDERS = ['google' => 'Google', 'apple' => 'Apple'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

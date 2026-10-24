<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Привязка учётки к Яндекс ID / VK ID (одна на провайдера). Не относится к организации — вход общий для всех */
class SocialAccount extends Model
{
    protected $fillable = ['user_id', 'provider', 'provider_user_id', 'email'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

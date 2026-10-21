<?php

namespace App\Models;

use App\Enums\AuthCodePurpose;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A hashed 6-digit code emailed to a user (App\Services\Auth\AuthCodeService). */
class AuthCode extends Model
{
    protected $connection = 'central';

    protected $fillable = ['user_id', 'purpose', 'code_hash', 'attempts', 'expires_at', 'sent_at'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'purpose' => AuthCodePurpose::class,
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

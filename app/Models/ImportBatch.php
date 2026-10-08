<?php

namespace App\Models;

use App\Enums\ImportBatchStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $fillable = [
        'user_id', 'original_filename', 'file_path', 'file_sha256',
        'status', 'stats', 'committed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ImportBatchStatus::class,
            'stats' => 'array',
            'committed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    public function foodSourceRecords(): HasMany
    {
        return $this->hasMany(FoodSourceRecord::class);
    }
}

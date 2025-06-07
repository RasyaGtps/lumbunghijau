<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'pickup_location',
        'total_weight',
        'total_price',
        'status',
        'image_path',
        'rejection_reason'
    ];

    protected $casts = [
        'total_weight' => 'decimal:2',
        'total_price' => 'decimal:2',
        'token_expires_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function balanceHistory()
    {
        return $this->hasOne(BalanceHistory::class);
    }

    public function isTokenExpired()
    {
        if (!$this->token_expires_at) {
            return false;
        }
        return now()->gt($this->token_expires_at);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'pickup_location',
        'total_weight',
        'total_price',
        'status',
        'qr_code_path',
        'verification_token',
        'token_expires_at'
    ];

    protected $casts = [
        'total_weight' => 'decimal:2',
        'total_price' => 'decimal:2',
        'token_expires_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function details()
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

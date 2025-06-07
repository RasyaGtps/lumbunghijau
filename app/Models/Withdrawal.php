<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    use HasFactory;

    protected $table = 'withdrawals';

    protected $fillable = [
        'user_id',
        'amount',
        'method',
        'virtual_account',
        'status',
        'expires_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expires_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
} 
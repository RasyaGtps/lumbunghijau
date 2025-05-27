<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Collector extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'assigned_area',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

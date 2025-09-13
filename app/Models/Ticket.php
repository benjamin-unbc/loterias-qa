<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket',
        'code',
        'date',
        'time',
        'user_id',
        'share_token',
        'share_token_expires_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lotteries()
    {
        return $this->hasMany(ApusModel::class, 'ticket');
    }

    public function playsSent()
    {
        return $this->hasMany(PlaysSentModel::class, 'ticket', 'ticket');
    }

    public function generateShareToken()
    {
        $this->share_token = Str::random(32);
        $this->share_token_expires_at = now()->addDays(7);
        $this->save();
    }
}

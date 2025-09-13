<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlaysSentModel extends Model
{
    use HasFactory;

    protected $table = 'plays_sent';

    protected $fillable = [
        'ticket',
        'user_id',
        'time',
        'timePlay',
        'type',
        'apu',
        'lot',
        'pay',
        'amount',
        'date',
        'code',
        'share_token',
        'status',
        'statusPlay'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function apus()
    {
        return $this->hasMany(ApusModel::class, 'ticket', 'ticket');
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket', 'ticket');
    }

    public function generateShareToken()
    {
        $this->share_token = bin2hex(random_bytes(16));
    }
}

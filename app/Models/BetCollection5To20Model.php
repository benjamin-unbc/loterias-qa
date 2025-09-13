<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BetCollection5To20Model extends Model
{
    use HasFactory;

    protected $table = 'bet_collection_5_20';

    protected $fillable = [
        'bet_amount',
        'payout_5_to_5',
        'payout_5_to_10',
        'payout_5_to_20'
    ];
}

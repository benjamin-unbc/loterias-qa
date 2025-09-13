<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BetCollection10To20Model extends Model
{
    use HasFactory;

    protected $table = 'bet_collection_10_20';

    protected $fillable = [
        'bet_amount',
        'payout_10_to_10',
        'payout_10_to_20',
        'payout_20_to_20'
    ];
}

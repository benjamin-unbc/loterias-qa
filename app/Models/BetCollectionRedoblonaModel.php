<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BetCollectionRedoblonaModel extends Model
{
    use HasFactory;

    protected $table = 'bet_collection_redoblona';

    protected $fillable = [
        'bet_amount',
        'payout_1_to_5',
        'payout_1_to_10',
        'payout_1_to_20'
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrizesModel extends Model
{
    use HasFactory;

    protected $table = 'prizes';

    protected $fillable = [
        'juega',
        'cobra_5',
        'cobra_10',
        'cobra_20'
    ];
}

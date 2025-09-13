<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuinielaModel extends Model
{
    use HasFactory;

    protected $table = 'quiniela';

    protected $fillable = [
        'juega',
        'cobra_1_cifra',
        'cobra_2_cifra',
        'cobra_3_cifra',
        'cobra_4_cifra'
    ];
}

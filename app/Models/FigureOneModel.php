<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FigureOneModel extends Model
{
    use HasFactory;

    protected $table = 'figureone';

    protected $fillable = [
        'juega',
        'cobra_5',
        'cobra_10',
        'cobra_20'
    ];
}

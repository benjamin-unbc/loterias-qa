<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FigureTwoModel extends Model
{
    use HasFactory;

    protected $table = 'figuretwo';

    protected $fillable = [
        'juega',
        'cobra_5',
        'cobra_10',
        'cobra_20'
    ];
}

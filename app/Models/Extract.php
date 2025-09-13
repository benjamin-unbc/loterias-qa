<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Extract extends Model
{
    use HasFactory;

    protected $table = 'extracts';

    protected $fillable = [
        'name',
        'date',
        'time',
    ];

    public function cities()
    {
        return $this->hasMany(City::class);
    }

    public function numbers()
    {
        return $this->hasManyThrough(Number::class, City::class);
    }
}

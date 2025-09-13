<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Number extends Model
{
    use HasFactory;

    protected $fillable = ['city_id', 'extract_id', 'index', 'value', 'date'];

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function extract()
    {
        return $this->belongsTo(Extract::class);
    }
}

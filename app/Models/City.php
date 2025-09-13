<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasFactory;

    protected $fillable = ['extract_id', 'name', 'code', 'time'];

    public function numbers()
    {
        return $this->hasMany(Number::class);
    }

    public function extract()
    {
        return $this->belongsTo(Extract::class, 'extract_id');
    }
}

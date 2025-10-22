<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Number extends Model
{
    use HasFactory;

    protected $fillable = ['city_id', 'extract_id', 'index', 'value', 'date'];

    /**
     * The "booted" method of the model.
     */
    protected static function booted()
    {
        static::created(function ($number) {
            \App\Observers\NumberObserver::created($number);
        });

        static::updated(function ($number) {
            \App\Observers\NumberObserver::updated($number);
        });
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function extract()
    {
        return $this->belongsTo(Extract::class);
    }
}

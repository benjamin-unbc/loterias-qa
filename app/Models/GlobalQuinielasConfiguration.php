<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GlobalQuinielasConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'city_name',
        'selected_schedules'
    ];

    protected $casts = [
        'selected_schedules' => 'array'
    ];
}

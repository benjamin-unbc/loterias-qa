<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSchedulePreference extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'city_name',
        'selected_schedules'
    ];
    
    protected $casts = [
        'selected_schedules' => 'array'
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Play extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'number',
        'position',
        'import',
        'lottery',
        'numberR',
        'positionR',
        'isChecked',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

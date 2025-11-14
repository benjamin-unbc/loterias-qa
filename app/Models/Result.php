<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Result extends Model
{
    use HasFactory;

    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'results';

    /**
     * Los atributos que se asignan de forma masiva.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'ticket',
        'lottery',
        'number',
        'position',
        'numero_g',
        'posicion_g',
        'numR',
        'posR',
        'num_g_r',
        'pos_g_r',
        'XA',
        'import',
        'aciert',
        'date',
        'time',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function playsSent()
    {
        return $this->belongsTo(PlaysSentModel::class, 'ticket', 'ticket');
    }
}

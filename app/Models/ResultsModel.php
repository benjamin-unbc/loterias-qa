<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResultsModel extends Model
{
    use HasFactory;

    protected $table = 'results'; // Nombre de la tabla

    protected $fillable = [
        'ticket',
        'lottery', // Changed from lotteries to lottery
        'number',
        'position',
        'numR',
        'posR',
        'xa',
        'import',
        'aciert', // Revertido a 'aciert'
        'date',
        'time', // Added time as per schema and Result.php
        'user_id', // Added user_id as per schema and Result.php
        'numero_g', // Added as per schema and Result.php
        'posicion_g', // Added as per schema and Result.php
        'num_g_r', // Added as per schema and Result.php
        'pos_g_r', // Added as per schema and Result.php
    ];

    protected $primaryKey = 'id';

    public $timestamps = true; // Schema has created_at and updated_at

    public function playsSent()
    {
        return $this->belongsTo(PlaysSentModel::class, 'ticket', 'ticket'); // Relación N:1 con PlaysSent
    }
}

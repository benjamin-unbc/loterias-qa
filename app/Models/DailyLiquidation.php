<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyLiquidation extends Model
{
    use HasFactory;

    protected $table = 'daily_liquidations';

    protected $fillable = [
        'date',
        'total_apus',
        'comision',
        'total_aciert',
        'total_gana_pase',
        'anteri',
        'ud_recibe',
        'ud_deja',
        'arrastre',
    ];

    protected $casts = [
        'date'              => 'date',
        'total_apus'        => 'decimal:2',
        'comision'          => 'decimal:2',
        'total_aciert'      => 'decimal:2',
        'total_gana_pase'   => 'decimal:2',
        'anteri'            => 'decimal:2',
        'ud_recibe'         => 'decimal:2',
        'ud_deja'           => 'decimal:2',
        'arrastre'          => 'decimal:2',
    ];
}

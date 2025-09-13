<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApusModel extends Model
{
    use HasFactory;

    protected $table = 'apus';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ticket',
        'user_id',
        'original_play_id', // <--- AÑADIDO AQUÍ
        'number',
        'position',
        'import',
        'lottery',
        'numberR',
        'positionR',
        'isChecked',
        'timeApu',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'isChecked' => 'boolean',
        'import' => 'decimal:2', // Considera castear el importe si es un decimal
    ];

    /**
     * Get the user that owns the apu.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the plays_sent record associated with the apu.
     */
    public function playsSent()
    {
        // Asumiendo que PlaysSentModel también usa 'ticket' como su clave principal o una clave única.
        // Si PlaysSentModel tiene un 'id' como PK, y 'ticket' es solo un campo, la relación podría necesitar ajuste
        // o ser indirecta a través del modelo Ticket.
        return $this->belongsTo(PlaysSentModel::class, 'ticket', 'ticket');
    }

    /**
     * Get the ticket record associated with the apu.
     */
    public function ticket()
    {
        // Similar a playsSent, depende de cómo esté definida la PK en Ticket.
        return $this->belongsTo(Ticket::class, 'ticket', 'ticket');
    }

    /**
     * Get the original play record that this apu belongs to.
     */
    public function originalPlay()
    {
        return $this->belongsTo(Play::class, 'original_play_id');
    }
}

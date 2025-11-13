<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'payment_date',
        'amount',
        'type',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    /**
     * Relación con el cliente
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Relación con el usuario que creó el pago
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

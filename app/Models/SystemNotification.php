<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'title',
        'message',
        'data',
        'is_read',
        'read_at'
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime'
    ];

    /**
     * Crear una notificación del sistema
     */
    public static function createNotification($type, $title, $message, $data = null)
    {
        return self::create([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'is_read' => false
        ]);
    }

    /**
     * Marcar como leída
     */
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now()
        ]);
    }

    /**
     * Obtener notificaciones no leídas
     */
    public static function getUnread()
    {
        return self::where('is_read', false)
                   ->orderBy('created_at', 'desc')
                   ->get();
    }

    /**
     * Obtener notificaciones recientes (últimas 10)
     */
    public static function getRecent()
    {
        return self::orderBy('created_at', 'desc')
                   ->limit(10)
                   ->get();
    }
}
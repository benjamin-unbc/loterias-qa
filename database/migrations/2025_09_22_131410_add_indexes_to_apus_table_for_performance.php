<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Agrega índices optimizados para mejorar el rendimiento de las consultas
     * en la tabla 'apus', especialmente para búsquedas por ticket y ordenamiento.
     */
    public function up(): void
    {
        Schema::table('apus', function (Blueprint $table) {
            // Índice principal para búsquedas por ticket (más usado)
            $table->index('ticket', 'idx_apus_ticket');
            
            // Índice compuesto para consultas con ORDER BY optimizado
            // Mejora significativamente: WHERE ticket = ? ORDER BY original_play_id, id
            $table->index(['ticket', 'original_play_id', 'id'], 'idx_apus_ticket_play_id');
            
            // Índice para filtros por usuario (ya implementado en filtros por cliente)
            $table->index('user_id', 'idx_apus_user_id');
            
            // Índice compuesto para consultas por usuario y ticket
            $table->index(['user_id', 'ticket'], 'idx_apus_user_ticket');
            
            // Índice para consultas por fecha (usado en liquidaciones)
            $table->index('created_at', 'idx_apus_created_at');
            
            // Índice compuesto para consultas por usuario y fecha
            $table->index(['user_id', 'created_at'], 'idx_apus_user_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apus', function (Blueprint $table) {
            // Eliminar índices en orden inverso
            $table->dropIndex('idx_apus_user_date');
            $table->dropIndex('idx_apus_created_at');
            $table->dropIndex('idx_apus_user_ticket');
            $table->dropIndex('idx_apus_user_id');
            $table->dropIndex('idx_apus_ticket_play_id');
            $table->dropIndex('idx_apus_ticket');
        });
    }
};
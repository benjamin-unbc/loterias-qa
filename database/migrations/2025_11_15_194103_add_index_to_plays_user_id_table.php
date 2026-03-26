<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * ✅ OPTIMIZACIÓN CRÍTICA: Agregar índice en user_id para mejorar rendimiento
     * Sin este índice, todas las consultas WHERE user_id = X hacen full table scan
     * Con el índice, las consultas son instantáneas incluso con miles de apuestas
     */
    public function up(): void
    {
        Schema::table('plays', function (Blueprint $table) {
            // Agregar índice en user_id (la columna más consultada)
            // Esto mejora drásticamente el rendimiento de:
            // - getAndSortPlays() 
            // - deleteRow()
            // - updateRow()
            // - getBasePlayForDerivation()
            $table->index('user_id', 'idx_plays_user_id');
            
            // ✅ OPTIMIZACIÓN ADICIONAL: Índice compuesto para consultas comunes
            // Mejora consultas que filtran por user_id y ordenan por id
            $table->index(['user_id', 'id'], 'idx_plays_user_id_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plays', function (Blueprint $table) {
            $table->dropIndex('idx_plays_user_id');
            $table->dropIndex('idx_plays_user_id_id');
        });
    }
};

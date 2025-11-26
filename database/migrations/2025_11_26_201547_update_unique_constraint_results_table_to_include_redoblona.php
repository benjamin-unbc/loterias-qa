<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * ✅ CORREGIDO: Actualizar índice único para incluir numR y posR
     * Esto permite que apuestas con diferentes redoblonas se inserten como resultados separados
     */
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table) {
            // Eliminar el índice único anterior que no incluía numR y posR
            $table->dropUnique('unique_result_per_ticket_lottery');
        });
        
        Schema::table('results', function (Blueprint $table) {
            // Crear nuevo índice único que incluye numR y posR
            // Esto permite múltiples resultados del mismo ticket/lotería/número/posición
            // pero con diferentes redoblonas (numR/posR)
            $table->unique(['ticket', 'lottery', 'number', 'position', 'numR', 'posR', 'date'], 'unique_result_per_ticket_lottery_with_redoblona');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            // Eliminar el nuevo índice único
            $table->dropUnique('unique_result_per_ticket_lottery_with_redoblona');
        });
        
        Schema::table('results', function (Blueprint $table) {
            // Restaurar el índice único anterior
            $table->unique(['ticket', 'lottery', 'number', 'position', 'date'], 'unique_result_per_ticket_lottery');
        });
    }
};

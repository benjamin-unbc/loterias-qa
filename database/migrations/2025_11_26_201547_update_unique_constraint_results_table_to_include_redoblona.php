<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
        // Verificar si el índice único anterior existe y eliminarlo de forma segura
        $indexExists = DB::select("SHOW INDEX FROM results WHERE Key_name = 'unique_result_per_ticket_lottery'");
        
        if (!empty($indexExists)) {
            Schema::table('results', function (Blueprint $table) {
                // Eliminar el índice único anterior que no incluía numR y posR
                $table->dropUnique('unique_result_per_ticket_lottery');
            });
        }
        
        // Limpiar duplicados existentes antes de crear el nuevo índice
        // Mantener solo el resultado con mayor premio (aciert) para cada combinación
        DB::statement("
            DELETE r1 FROM results r1
            INNER JOIN results r2 
            WHERE r1.id < r2.id
            AND r1.ticket = r2.ticket
            AND r1.lottery = r2.lottery
            AND r1.number = r2.number
            AND r1.position = r2.position
            AND COALESCE(r1.numR, '') = COALESCE(r2.numR, '')
            AND COALESCE(r1.posR, '') = COALESCE(r2.posR, '')
            AND r1.date = r2.date
        ");
        
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

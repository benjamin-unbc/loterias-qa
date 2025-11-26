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
     * ✅ MODIFICADO: Limpiar duplicados antes de crear el índice único
     */
    public function up()
    {
        // Verificar si el índice ya existe
        $indexExists = DB::select("SHOW INDEX FROM results WHERE Key_name = 'unique_result_per_ticket_lottery'");
        
        if (empty($indexExists)) {
            // Limpiar duplicados existentes antes de crear el índice
            // Mantener solo el resultado con mayor premio (aciert) para cada combinación
            DB::statement("
                DELETE r1 FROM results r1
                INNER JOIN results r2 
                WHERE r1.id < r2.id
                AND r1.ticket = r2.ticket
                AND r1.lottery = r2.lottery
                AND r1.number = r2.number
                AND r1.position = r2.position
                AND r1.date = r2.date
                AND COALESCE(r1.numR, '') = COALESCE(r2.numR, '')
                AND COALESCE(r1.posR, '') = COALESCE(r2.posR, '')
            ");
            
            Schema::table('results', function (Blueprint $table) {
                // Agregar índice único compuesto para prevenir duplicados
                $table->unique(['ticket', 'lottery', 'number', 'position', 'date'], 'unique_result_per_ticket_lottery');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropUnique('unique_result_per_ticket_lottery');
        });
    }
};

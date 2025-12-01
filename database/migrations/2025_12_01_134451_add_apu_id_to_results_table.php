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
     * ✅ Agrega apu_id a la tabla results para permitir múltiples resultados
     * del mismo número cuando provienen de diferentes APUs
     */
    public function up(): void
    {
        // Verificar si el índice único actual existe y eliminarlo
        $indexExists = DB::select("SHOW INDEX FROM results WHERE Key_name = 'unique_result_per_ticket_lottery_with_redoblona'");
        
        if (!empty($indexExists)) {
            Schema::table('results', function (Blueprint $table) {
                $table->dropUnique('unique_result_per_ticket_lottery_with_redoblona');
            });
        }
        
        // Agregar columna apu_id
        Schema::table('results', function (Blueprint $table) {
            $table->unsignedBigInteger('apu_id')->nullable()->after('user_id');
            $table->foreign('apu_id')
                ->references('id')->on('apus')
                ->onDelete('cascade');
        });
        
        // Crear nuevo índice único que incluye apu_id
        // Esto permite múltiples resultados del mismo ticket/lotería/número/posición
        // cuando provienen de diferentes APUs
        Schema::table('results', function (Blueprint $table) {
            $table->unique(['ticket', 'lottery', 'number', 'position', 'numR', 'posR', 'date', 'apu_id'], 'unique_result_per_apu');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar el nuevo índice único
        $indexExists = DB::select("SHOW INDEX FROM results WHERE Key_name = 'unique_result_per_apu'");
        if (!empty($indexExists)) {
            Schema::table('results', function (Blueprint $table) {
                $table->dropUnique('unique_result_per_apu');
            });
        }
        
        // Eliminar columna apu_id
        Schema::table('results', function (Blueprint $table) {
            $table->dropForeign(['apu_id']);
            $table->dropColumn('apu_id');
        });
        
        // Restaurar el índice único anterior
        Schema::table('results', function (Blueprint $table) {
            $table->unique(['ticket', 'lottery', 'number', 'position', 'numR', 'posR', 'date'], 'unique_result_per_ticket_lottery_with_redoblona');
        });
    }
};

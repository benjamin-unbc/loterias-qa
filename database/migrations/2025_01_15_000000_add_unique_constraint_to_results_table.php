<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('results', function (Blueprint $table) {
            // Agregar índice único compuesto para prevenir duplicados
            $table->unique(['ticket', 'lottery', 'number', 'position', 'date'], 'unique_result_per_ticket_lottery');
        });
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

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apus', function (Blueprint $table) { // Usa 'apus'
            // El nombre de la foreign key suele ser nombredetabla_nombredelacolumna_foreign
            $table->dropForeign(['original_play_id']); // Asume nombre autogenerado por Laravel
            $table->dropColumn('original_play_id');
        });
    }
};

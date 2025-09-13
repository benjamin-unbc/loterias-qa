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
        Schema::table('apus', function (Blueprint $table) {
             $table->boolean('isChecked')->default(false)->after('positionR'); // O después de la columna que prefieras            // Agregar la columna isChecked
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apus', function (Blueprint $table) {
            $table->dropColumn('isChecked');
        });
    }
};

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
        Schema::create('daily_liquidations', function (Blueprint $table) {
            $table->id();
            $table->date('date'); // Fecha de la liquidación
            $table->decimal('total_apus', 15, 2)->default(0); // Suma total de Apus del día
            $table->decimal('comision', 15, 2)->default(0);
            $table->decimal('total_aciert', 15, 2)->default(0); // Total de aciertos del día
            $table->decimal('total_gana_pase', 15, 2)->default(0); // UD DEJA o GENER. DEJA (hoy)
            $table->decimal('anteri', 15, 2)->default(0); // UD DEJA del día anterior
            $table->decimal('ud_recibe', 15, 2)->default(0); // UD RECIBE (hoy)
            $table->decimal('ud_deja', 15, 2)->default(0); // UD DEJA (hoy)
            $table->decimal('arrastre', 15, 2)->default(0); // ARRastre = anteri + total_gana_pase
            $table->timestamps(); // created_at y updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_liquidations');
    }
};

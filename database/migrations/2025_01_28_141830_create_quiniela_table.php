<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuinielaTable extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('quiniela', function (Blueprint $table) {
            $table->id(); // Clave primaria auto-incremental
            $table->decimal('juega', 8, 2); // Indica si el usuario está jugando
            $table->decimal('cobra_1_cifra', 8, 2); // Pago para 1 cifra
            $table->decimal('cobra_2_cifra', 8, 2); // Pago para 2 cifras
            $table->decimal('cobra_3_cifra', 8, 2); // Pago para 3 cifras
            $table->decimal('cobra_4_cifra', 8, 2)->nullable(); // Pago para 4 cifras, opcional si no se proporciona
            $table->timestamps(); // Campos 'created_at' y 'updated_at'
        });
    }

    /**
     * Revertir las migraciones.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('quiniela');
    }
}

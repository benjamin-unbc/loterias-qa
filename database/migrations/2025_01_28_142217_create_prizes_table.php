<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePrizesTable extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('prizes', function (Blueprint $table) {
            $table->id(); // Clave primaria auto-incremental
            $table->decimal('juega', 8, 2); // Indica si el usuario está participando
            $table->decimal('cobra_5', 8, 2); // Pago para 5
            $table->decimal('cobra_10', 8, 2); // Pago para 10
            $table->decimal('cobra_20', 8, 2); // Pago para 20
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
        Schema::dropIfExists('prizes');
    }
}

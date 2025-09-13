<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFigureoneTable extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('figureone', function (Blueprint $table) {
            $table->id(); // Clave primaria auto-incremental
            $table->decimal('juega', 8, 2); // Puedes ajustar el tipo de dato según tus necesidades
            $table->decimal('cobra_5', 8, 2); // Para montos monetarios
            $table->decimal('cobra_10', 8, 2);
            $table->decimal('cobra_20', 8, 2);
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
        Schema::dropIfExists('figureone');
    }
}

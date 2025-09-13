<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateResultsTable extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');
            $table->string('ticket');
            $table->string('lottery')->nullable();
            $table->string('number');
            $table->string('position');
            $table->string('numR')->nullable();
            $table->string('posR')->nullable();
            $table->string('XA')->nullable();
            $table->string('import');
            $table->string('aciert');
            $table->date('date');
            $table->string('time');
            $table->timestamps();
        });
    }

    /**
     * Revierte las migraciones.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('results');
    }
}

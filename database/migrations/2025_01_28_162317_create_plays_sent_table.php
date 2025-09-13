<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlaysSentTable extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('plays_sent', function (Blueprint $table) {
            $table->id();
            $table->string('ticket', 20);
            $table->foreign('ticket')
                ->references('ticket')->on('tickets')
                ->onDelete('cascade');
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');

            // Otros campos
            $table->string('time');
            $table->string('timePlay');
            $table->string('type')->default('J');
            $table->string('apu')->nullable();
            $table->string('lot')->nullable();
            $table->decimal('pay', 10, 2);
            $table->decimal('amount', 10, 2);
            $table->date('date');
            $table->string('code');
            $table->string('share_token')->nullable();
            $table->string('status')->default('A');
            $table->string('statusPlay')->default(('A'));
            $table->timestamps();
        });
    }

    /**
     * Revertir las migraciones.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('plays_sent');
    }
}

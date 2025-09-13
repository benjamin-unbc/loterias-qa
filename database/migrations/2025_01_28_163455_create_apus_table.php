<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('apus', function (Blueprint $table) {
            $table->id();
            $table->string('ticket', 20);
            $table->foreign('ticket')->references('ticket')->on('plays_sent')->onDelete('cascade');
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');
            $table->string('number');
            $table->string('position');
            $table->string('import');
            $table->string('lottery');
            $table->string('numberR')->nullable();
            $table->string('positionR')->nullable();
            $table->time('timeApu');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('apus');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCitiesTable extends Migration
{
    public function up()
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('extract_id');
            $table->foreign('extract_id')
                ->references('id')
                ->on('extracts')
                ->onDelete('cascade');
            $table->string('name');
            $table->string('code')->unique()->collation('utf8mb4_bin');
            $table->string('time');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('cities');
    }
}

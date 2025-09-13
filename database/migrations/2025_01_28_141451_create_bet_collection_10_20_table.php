<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBetCollection1020Table extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bet_collection_10_20', function (Blueprint $table) {
            $table->id();
            $table->decimal('bet_amount', 10, 2);
            $table->decimal('payout_10_to_10', 10, 2);
            $table->decimal('payout_10_to_20', 10, 2);
            $table->decimal('payout_20_to_20', 10, 2);
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
        Schema::dropIfExists('bet_collection_10_20');
    }
}

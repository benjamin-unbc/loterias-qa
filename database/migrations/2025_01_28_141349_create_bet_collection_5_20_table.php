<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBetCollection520Table extends Migration
{
    /**
     * Ejecutar las migraciones.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bet_collection_5_20', function (Blueprint $table) {
            $table->id();
            $table->decimal('bet_amount', 10, 2);
            $table->decimal('payout_5_to_5', 10, 2);
            $table->decimal('payout_5_to_10', 10, 2);
            $table->decimal('payout_5_to_20', 10, 2);
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
        Schema::dropIfExists('bet_collection_5_20');
    }
}

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
        Schema::table('Results', function (Blueprint $table) {
            $table->string('numero_g')->nullable()->after('position');
            $table->integer('posicion_g')->nullable()->after('numero_g');
            $table->string('num_g_r')->nullable()->after('posR');
            $table->integer('pos_g_r')->nullable()->after('num_g_r');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('Results', function (Blueprint $table) {
            $table->dropColumn(['numero_g', 'posicion_g', 'num_g_r', 'pos_g_r']);
        });
    }
};

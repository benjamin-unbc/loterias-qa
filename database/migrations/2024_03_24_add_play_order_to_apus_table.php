<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('apus', function (Blueprint $table) {
            $table->integer('play_order')->nullable()->after('timeApu');
        });
    }

    public function down()
    {
        Schema::table('apus', function (Blueprint $table) {
            $table->dropColumn('play_order');
        });
    }
}; 
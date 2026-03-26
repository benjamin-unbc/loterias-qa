<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plays_sent', function (Blueprint $table) {
            $table->string('lot', 2000)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plays_sent', function (Blueprint $table) {
            $table->string('lot', 255)->change();
        });
    }
};

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
        Schema::create('global_quinielas_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('city_name');
            $table->json('selected_schedules');
            $table->timestamps();
            
            $table->unique('city_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('global_quinielas_configurations');
    }
};

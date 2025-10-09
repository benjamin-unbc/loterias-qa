<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Verificar si ya existe Montevideo Vespertino
        $exists = DB::table('cities')
            ->where('extract_id', 4)
            ->where('name', 'MONTEVIDEO')
            ->where('code', 'ORO1800')
            ->exists();
            
        if (!$exists) {
            // Agregar Montevideo Vespertino (extract_id = 4)
            DB::table('cities')->insert([
                'extract_id' => 4,
                'name' => 'MONTEVIDEO',
                'code' => 'ORO1800',
                'time' => '18:00',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar Montevideo Vespertino
        DB::table('cities')
            ->where('extract_id', 4)
            ->where('name', 'MONTEVIDEO')
            ->where('code', 'ORO1800')
            ->delete();
    }
};

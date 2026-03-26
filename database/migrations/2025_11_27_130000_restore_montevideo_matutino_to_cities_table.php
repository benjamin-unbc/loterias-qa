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
        // Verificar si ya existe el registro antes de insertarlo
        $exists = DB::table('cities')
            ->where('name', 'MONTEVIDEO')
            ->where('code', 'ORO1500')
            ->where('extract_id', 3)
            ->exists();
        
        if (!$exists) {
            DB::table('cities')->insert([
                'extract_id' => 3,
                'name' => 'MONTEVIDEO',
                'code' => 'ORO1500',
                'time' => '15:00',
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
        DB::table('cities')
            ->where('name', 'MONTEVIDEO')
            ->where('code', 'ORO1500')
            ->where('extract_id', 3) // Matutina
            ->delete();
    }
};


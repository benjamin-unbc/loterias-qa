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
        // Eliminar ORO1800 (Montevideo 18:00, Vespertina, extract_id 4)
        DB::table('cities')
            ->where('name', 'MONTEVIDEO')
            ->where('code', 'ORO1800')
            ->where('extract_id', 4) // Vespertina
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restaurar ORO1800 si se hace rollback
        $exists = DB::table('cities')
            ->where('name', 'MONTEVIDEO')
            ->where('code', 'ORO1800')
            ->where('extract_id', 4)
            ->exists();
        
        if (!$exists) {
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
};


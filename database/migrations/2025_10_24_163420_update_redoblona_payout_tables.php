<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateRedoblonaPayoutTables extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Actualizar tabla bet_collection_redoblona con los valores correctos
        // A los 1 todo a los 5 Cobra: $1.00 → $1,280.00
        // A los 1 todo a los 10 Cobra: $1.00 → $640.00  
        // A los 1 todo a los 20 Cobra: $1.00 → $336.84
        DB::table('bet_collection_redoblona')->updateOrInsert(
            ['id' => 1],
            [
                'bet_amount' => 1.00,
                'payout_1_to_5' => 1280.00,
                'payout_1_to_10' => 640.00,
                'payout_1_to_20' => 336.84,
                'updated_at' => now(),
                'created_at' => now()
            ]
        );

        // Actualizar tabla bet_collection_5_20 con los valores correctos
        // A los 5 todo a los 5 Cobra: $1.00 → $256.00
        // A los 5 todo a los 10 Cobra: $1.00 → $128.00
        // A los 5 todo a los 20 Cobra: $1.00 → $64.00
        DB::table('bet_collection_5_20')->updateOrInsert(
            ['id' => 1],
            [
                'bet_amount' => 1.00,
                'payout_5_to_5' => 256.00,
                'payout_5_to_10' => 128.00,
                'payout_5_to_20' => 64.00,
                'updated_at' => now(),
                'created_at' => now()
            ]
        );

        // Actualizar tabla bet_collection_10_20 con los valores correctos
        // A los 10 todo a los 10 Cobra: $1.00 → $64.00
        // A los 10 todo a los 20 Cobra: $1.00 → $32.00
        // A los 20 todo a los 20 Cobra: $1.00 → $16.00
        DB::table('bet_collection_10_20')->updateOrInsert(
            ['id' => 1],
            [
                'bet_amount' => 1.00,
                'payout_10_to_10' => 64.00,
                'payout_10_to_20' => 32.00,
                'payout_20_to_20' => 16.00,
                'updated_at' => now(),
                'created_at' => now()
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir a valores anteriores si es necesario
        DB::table('bet_collection_redoblona')->where('id', 1)->delete();
        DB::table('bet_collection_5_20')->where('id', 1)->delete();
        DB::table('bet_collection_10_20')->where('id', 1)->delete();
    }
}
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BetCollectionRedoblonaSeeder extends Seeder
{
    /**
     * Ejecutar los seeds de la base de datos.
     *
     * @return void
     */
    public function run()
    {
        $now = Carbon::now();
        $data = [
            [
                'bet_amount'    => 25.00,
                'payout_1_to_5' => 1280.00,
                'payout_1_to_10' => 640.00,
                'payout_1_to_20' => 336.84,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'bet_amount'    => 10.00,
                'payout_1_to_5' => 12800.00,
                'payout_1_to_10' => 6400.00,
                'payout_1_to_20' => 3368.40,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'bet_amount'    => 5.00,
                'payout_1_to_5' => 6400.00,
                'payout_1_to_10' => 3200.00,
                'payout_1_to_20' => 1684.20,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'bet_amount'    => 2.50,
                'payout_1_to_5' => 3200.00,
                'payout_1_to_10' => 1600.50,
                'payout_1_to_20' => 842.10,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'bet_amount'    => 2.00,
                'payout_1_to_5' => 2560.00,
                'payout_1_to_10' => 1280.00,
                'payout_1_to_20' => 673.68,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'bet_amount'    => 1.00,
                'payout_1_to_5' => 1280.00,
                'payout_1_to_10' => 640.00,
                'payout_1_to_20' => 336.84,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
        ];
        DB::table('bet_collection_redoblona')->insert($data);
    }
}

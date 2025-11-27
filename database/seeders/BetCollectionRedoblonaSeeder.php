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
                'payout_1_to_5' => 1600.00,
                'payout_1_to_10' => 800.00,
                'payout_1_to_20' => 400.00,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'bet_amount'    => 10.00,
                'payout_1_to_5' => 640.00,
                'payout_1_to_10' => 320.00,
                'payout_1_to_20' => 160.00,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'bet_amount'    => 5.00,
                'payout_1_to_5' => 320.00,
                'payout_1_to_10' => 160.00,
                'payout_1_to_20' => 80.00,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'bet_amount'    => 2.50,
                'payout_1_to_5' => 160.00,
                'payout_1_to_10' => 80.00,
                'payout_1_to_20' => 40.00,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'bet_amount'    => 2.00,
                'payout_1_to_5' => 128.00,
                'payout_1_to_10' => 64.00,
                'payout_1_to_20' => 32.00,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'bet_amount'    => 1.00,
                'payout_1_to_5' => 64.00,
                'payout_1_to_10' => 32.00,
                'payout_1_to_20' => 16.00,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
        ];
        DB::table('bet_collection_redoblona')->insert($data);
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BetCollection1020Seeder extends Seeder
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
                'bet_amount'      => 25.00,
                'payout_10_to_10' => 64.00,
                'payout_10_to_20' => 32.00,
                'payout_20_to_20' => 16.00,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [
                'bet_amount'      => 10.00,
                'payout_10_to_10' => 640.00,
                'payout_10_to_20' => 320.00,
                'payout_20_to_20' => 160.00,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [
                'bet_amount'      => 5.00,
                'payout_10_to_10' => 320.00,
                'payout_10_to_20' => 160.00,
                'payout_20_to_20' => 80.00,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [
                'bet_amount'      => 2.50,
                'payout_10_to_10' => 160.00,
                'payout_10_to_20' => 80.00,
                'payout_20_to_20' => 40.00,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [
                'bet_amount'      => 2.00,
                'payout_10_to_10' => 128.00,
                'payout_10_to_20' => 64.00,
                'payout_20_to_20' => 32.00,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [
                'bet_amount'      => 1.00,
                'payout_10_to_10' => 64.00,
                'payout_10_to_20' => 32.00,
                'payout_20_to_20' => 16.00,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
        ];
        DB::table('bet_collection_10_20')->insert($data);
    }
}

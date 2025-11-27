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
                'payout_10_to_10' => 32000.00,
                'payout_10_to_20' => 16000.00,
                'payout_20_to_20' => 8421.00,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [
                'bet_amount'      => 10.00,
                'payout_10_to_10' => 12800.00,
                'payout_10_to_20' => 6400.00,
                'payout_20_to_20' => 3368.40,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [
                'bet_amount'      => 5.00,
                'payout_10_to_10' => 6400.00,
                'payout_10_to_20' => 3200.00,
                'payout_20_to_20' => 1684.20,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [
                'bet_amount'      => 2.50,
                'payout_10_to_10' => 3200.00,
                'payout_10_to_20' => 1600.50,
                'payout_20_to_20' => 842.10,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [
                'bet_amount'      => 2.00,
                'payout_10_to_10' => 2560.00,
                'payout_10_to_20' => 1280.00,
                'payout_20_to_20' => 673.68,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [
                'bet_amount'      => 1.00,
                'payout_10_to_10' => 1280.00,
                'payout_10_to_20' => 640.00,
                'payout_20_to_20' => 336.84,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
        ];
        DB::table('bet_collection_10_20')->insert($data);
    }
}

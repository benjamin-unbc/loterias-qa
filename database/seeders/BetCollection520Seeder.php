<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BetCollection520Seeder extends Seeder
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
                'bet_amount'     => 25.00,
                'payout_5_to_5'  => 6400.00,
                'payout_5_to_10' => 3200.00,
                'payout_5_to_20' => 1600.00,
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
            [
                'bet_amount'     => 10.00,
                'payout_5_to_5'  => 2560.00,
                'payout_5_to_10' => 1280.00,
                'payout_5_to_20' => 640.00,
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
            [
                'bet_amount'     => 5.00,
                'payout_5_to_5'  => 1280.00,
                'payout_5_to_10' => 640.00,
                'payout_5_to_20' => 320.00,
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
            [
                'bet_amount'     => 2.50,
                'payout_5_to_5'  => 640.00,
                'payout_5_to_10' => 320.00,
                'payout_5_to_20' => 160.00,
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
            [
                'bet_amount'     => 2.00,
                'payout_5_to_5'  => 512.00,
                'payout_5_to_10' => 256.00,
                'payout_5_to_20' => 128.00,
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
            [
                'bet_amount'     => 1.00,
                'payout_5_to_5'  => 256.00,
                'payout_5_to_10' => 128.00,
                'payout_5_to_20' => 64.00,
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
        ];
        DB::table('bet_collection_5_20')->insert($data);
    }
}

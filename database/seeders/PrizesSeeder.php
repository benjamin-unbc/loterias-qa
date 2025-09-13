<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PrizesSeeder extends Seeder
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
                'juega' => 1.00,
                'cobra_5' => 14.00,
                'cobra_10' => 7.00,
                'cobra_20' => 3.50,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        ];
        DB::table('prizes')->insert($data);
    }
}

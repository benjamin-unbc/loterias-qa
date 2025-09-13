<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FiguretwoSeeder extends Seeder
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
                'cobra_5' => 700.00,
                'cobra_10' => 350.00,
                'cobra_20' => 175.00,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        ];
        DB::table('figuretwo')->insert($data);
    }
}

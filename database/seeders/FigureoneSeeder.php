<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FigureoneSeeder extends Seeder
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
                'cobra_5' => 7.00,
                'cobra_10' => 70.00,
                'cobra_20' => 600.00,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        ];
        DB::table('figureone')->insert($data);
    }
}

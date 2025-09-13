<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class QuinielaSeeder extends Seeder
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
                'cobra_1_cifra' => 120.00,
                'cobra_2_cifra' => 60.00,
                'cobra_3_cifra' => 30.00,
                'cobra_4_cifra' => 0.00,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];
        DB::table('quiniela')->insert($data);
    }
}

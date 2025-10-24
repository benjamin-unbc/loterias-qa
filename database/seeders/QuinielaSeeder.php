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
                'cobra_1_cifra' => 7.00,
                'cobra_2_cifra' => 70.00,
                'cobra_3_cifra' => 600.00,
                'cobra_4_cifra' => 3500.00,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];
        DB::table('quiniela')->insert($data);
    }
}

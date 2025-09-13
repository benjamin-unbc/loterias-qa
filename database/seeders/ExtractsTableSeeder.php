<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ExtractsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $date = Carbon::today()->toDateString();
        $time = Carbon::now()->toTimeString();

        DB::table('extracts')->insert([
            [
                'name'  => 'PREVIA',
                'date'  => $date,
                'time'  => $time,
            ],
            [
                'name'  => 'PRIMERO',
                'date'  => $date,
                'time'  => $time,
            ],
            [
                'name'  => 'MATUTINO',
                'date'  => $date,
                'time'  => $time,
            ],
            [
                'name'  => 'VESPERTINO',
                'date'  => $date,
                'time'  => $time,
            ],
            [
                'name'  => 'NOCTURNO',
                'date'  => $date,
                'time'  => $time,
            ],
        ]);
    }
}

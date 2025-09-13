<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ResultsTableSeeder extends Seeder
{
    /**
     * Ejecuta el _seeder_ para insertar información en la tabla results.
     *
     * @return void
     */
    public function run()
    {
        DB::table('results')->insert([
            'user_id'   => 20, // Asegúrate de que exista un usuario con este id
            'ticket'    => 'TICKET123',
            'lottery'   => 'Lotería Nacional', // Puedes ajustar o dejar nulo
            'number'    => '1234',
            'position'  => '1', // Ejemplo de posición
            'numR'      => '5678', // Este campo es opcional (nullable)
            'posR'      => '2',    // Este campo es opcional (nullable)
            'XA'        => 'Valor XA', // Este campo es opcional (nullable)
            'import'    => '100',  // Monto o importe
            'aciert'    => '1',    // Ejemplo de aciertos
            'date'      => Carbon::now()->format('Y-m-d'),
            'time'      => Carbon::now()->format('H:i:s'),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }
}

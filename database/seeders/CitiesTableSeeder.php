<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitiesTableSeeder extends Seeder
{
    public function run()
    {
        $cities = [
            ['extract_id' => 1, 'name' => 'CIUDAD', 'code' => 'NAC1015', 'time' => '10:15'],
            ['extract_id' => 1, 'name' => 'SANTA FE', 'code' => 'SFE1015', 'time' => '10:15'],
            ['extract_id' => 1, 'name' => 'PROVINCIA', 'code' => 'PRO1015', 'time' => '10:15'],
            ['extract_id' => 1, 'name' => 'ENTRE RIOS', 'code' => 'RIO1015', 'time' => '10:15'],
            ['extract_id' => 1, 'name' => 'CORDOBA', 'code' => 'COR1015', 'time' => '10:15'],
            ['extract_id' => 1, 'name' => 'CORRIENTES', 'code' => 'CTE1015', 'time' => '10:15'],
            ['extract_id' => 1, 'name' => 'CHACO', 'code' => 'CHA1015', 'time' => '10:15'],
            ['extract_id' => 1, 'name' => 'NEUQUEN', 'code' => 'NQN1015', 'time' => '10:15'],
            ['extract_id' => 1, 'name' => 'MISIONES', 'code' => 'MIS1030', 'time' => '10:30'],
            ['extract_id' => 1, 'name' => 'MENDOZA', 'code' => 'MZA1015', 'time' => '10:15'],
            ['extract_id' => 1, 'name' => 'Río Negro', 'code' => 'Rio1015', 'time' => '10:15'],
            ['extract_id' => 1, 'name' => 'Tucuman', 'code' => 'Tucu1130', 'time' => '11:30'],
            ['extract_id' => 1, 'name' => 'Santiago', 'code' => 'San1015', 'time' => '10:15'],


            ['extract_id' => 2, 'name' => 'ENTRE RIOS', 'code' => 'RIO1200', 'time' => '12:00'],
            ['extract_id' => 2, 'name' => 'CIUDAD', 'code' => 'NAC1200', 'time' => '12:00'],
            ['extract_id' => 2, 'name' => 'SANTA FE', 'code' => 'SFE1200', 'time' => '12:00'],
            ['extract_id' => 2, 'name' => 'CORDOBA', 'code' => 'COR1200', 'time' => '12:00'],
            ['extract_id' => 2, 'name' => 'PROVINCIA', 'code' => 'PRO1200', 'time' => '12:00'],
            // NOTA: Montevideo NO tiene "Primera" - solo tiene Vespertina (extract_id 4) y Nocturna (extract_id 5)
            ['extract_id' => 2, 'name' => 'CORRIENTES', 'code' => 'CTE1200', 'time' => '12:00'],
            ['extract_id' => 2, 'name' => 'CHACO', 'code' => 'CHA1200', 'time' => '12:00'],
            ['extract_id' => 2, 'name' => 'MENDOZA', 'code' => 'MZA1200', 'time' => '12:00'],
            ['extract_id' => 2, 'name' => 'NEUQUEN', 'code' => 'NQN1200', 'time' => '12:00'],
            ['extract_id' => 2, 'name' => 'MISIONES', 'code' => 'MIS1215', 'time' => '12:15'],
            ['extract_id' => 2, 'name' => 'JUJUY', 'code' => 'JUJ1200', 'time' => '12:00'],
            ['extract_id' => 2, 'name' => 'SALTA', 'code' => 'Salt1130', 'time' => '11:30'],
            ['extract_id' => 2, 'name' => 'Río Negro', 'code' => 'Rio1200', 'time' => '12:00'],
            ['extract_id' => 2, 'name' => 'Tucuman', 'code' => 'Tucu1430', 'time' => '14:30'],
            ['extract_id' => 2, 'name' => 'Santiago', 'code' => 'San1200', 'time' => '12:00'],


            ['extract_id' => 3, 'name' => 'CIUDAD', 'code' => 'NAC1500', 'time' => '15:00'],
            ['extract_id' => 3, 'name' => 'SANTA FE', 'code' => 'SFE1500', 'time' => '15:00'],
            ['extract_id' => 3, 'name' => 'PROVINCIA', 'code' => 'PRO1500', 'time' => '15:00'],
            ['extract_id' => 3, 'name' => 'ENTRE RIOS', 'code' => 'RIO1500', 'time' => '15:00'],
            ['extract_id' => 3, 'name' => 'CORDOBA', 'code' => 'COR1500', 'time' => '15:00'],
            ['extract_id' => 3, 'name' => 'CORRIENTES', 'code' => 'CTE1500', 'time' => '15:00'],
            ['extract_id' => 3, 'name' => 'CHACO', 'code' => 'CHA1500', 'time' => '15:00'],
            ['extract_id' => 3, 'name' => 'MENDOZA', 'code' => 'MZA1500', 'time' => '15:00'],
            ['extract_id' => 3, 'name' => 'NEUQUEN', 'code' => 'NQN1500', 'time' => '15:00'],
            ['extract_id' => 3, 'name' => 'MISIONES', 'code' => 'MIS1500', 'time' => '15:00'],
            ['extract_id' => 3, 'name' => 'JUJUY', 'code' => 'JUJ1500', 'time' => '15:00'],
            ['extract_id' => 3, 'name' => 'SALTA', 'code' => 'Salt1400', 'time' => '14:00'],
            ['extract_id' => 3, 'name' => 'Río Negro', 'code' => 'Rio1500', 'time' => '15:00'],
            ['extract_id' => 3, 'name' => 'Tucuman', 'code' => 'Tucu1730', 'time' => '17:30'],
            ['extract_id' => 3, 'name' => 'Santiago', 'code' => 'San1500', 'time' => '15:00'],


            ['extract_id' => 4, 'name' => 'PROVINCIA', 'code' => 'PRO1800', 'time' => '18:00'],
            ['extract_id' => 4, 'name' => 'CIUDAD', 'code' => 'NAC1800', 'time' => '18:00'],
            ['extract_id' => 4, 'name' => 'CORDOBA', 'code' => 'COR1800', 'time' => '18:00'],
            ['extract_id' => 4, 'name' => 'ENTRE RIOS', 'code' => 'RIO1800', 'time' => '18:00'],
            ['extract_id' => 4, 'name' => 'SANTA FE', 'code' => 'SFE1800', 'time' => '18:00'],
            ['extract_id' => 4, 'name' => 'CORRIENTES', 'code' => 'CTE1800', 'time' => '18:00'],
            ['extract_id' => 4, 'name' => 'CHACO', 'code' => 'CHA1800', 'time' => '18:00'],
            ['extract_id' => 4, 'name' => 'MENDOZA', 'code' => 'MZA1800', 'time' => '18:00'],
            ['extract_id' => 4, 'name' => 'NEUQUEN', 'code' => 'NQN1800', 'time' => '18:00'],
            ['extract_id' => 4, 'name' => 'MISIONES', 'code' => 'MIS1800', 'time' => '18:00'],
            ['extract_id' => 4, 'name' => 'JUJUY', 'code' => 'JUJ1800', 'time' => '18:00'],
            ['extract_id' => 4, 'name' => 'SALTA', 'code' => 'Salt1730', 'time' => '17:30'],
            ['extract_id' => 4, 'name' => 'Río Negro', 'code' => 'Rio1800', 'time' => '18:00'],
            ['extract_id' => 4, 'name' => 'Tucuman', 'code' => 'Tucu1930', 'time' => '19:30'],
            ['extract_id' => 4, 'name' => 'Santiago', 'code' => 'San1945', 'time' => '19:45'],
            ['extract_id' => 3, 'name' => 'MONTEVIDEO', 'code' => 'ORO1500', 'time' => '15:00'],


            ['extract_id' => 5, 'name' => 'CORDOBA', 'code' => 'COR2100', 'time' => '21:00'],
            ['extract_id' => 5, 'name' => 'SANTA FE', 'code' => 'SFE2100', 'time' => '21:00'],
            ['extract_id' => 5, 'name' => 'CIUDAD', 'code' => 'NAC2100', 'time' => '21:00'],
            ['extract_id' => 5, 'name' => 'MONTEVIDEO', 'code' => 'ORO2100', 'time' => '21:00'],
            ['extract_id' => 5, 'name' => 'PROVINCIA', 'code' => 'PRO2100', 'time' => '21:00'],
            ['extract_id' => 5, 'name' => 'ENTRE RIOS', 'code' => 'RIO2100', 'time' => '21:00'],
            ['extract_id' => 5, 'name' => 'CORRIENTES', 'code' => 'CTE2100', 'time' => '21:00'],
            ['extract_id' => 5, 'name' => 'CHACO', 'code' => 'CHA2100', 'time' => '21:00'],
            ['extract_id' => 5, 'name' => 'MENDOZA', 'code' => 'MZA2100', 'time' => '21:00'],
            ['extract_id' => 5, 'name' => 'NEUQUEN', 'code' => 'NQN2100', 'time' => '21:00'],
            ['extract_id' => 5, 'name' => 'JUJUY', 'code' => 'JUJ2100', 'time' => '21:00'],
            ['extract_id' => 5, 'name' => 'Río Negro', 'code' => 'Rio2100', 'time' => '21:00'],
            ['extract_id' => 5, 'name' => 'SALTA', 'code' => 'Salt2100', 'time' => '21:00'],
            ['extract_id' => 5, 'name' => 'Tucuman', 'code' => 'Tucu2200', 'time' => '22:00'],
            ['extract_id' => 5, 'name' => 'MISIONES', 'code' => 'MIS2115', 'time' => '21:15'],
            ['extract_id' => 5, 'name' => 'Santiago', 'code' => 'San2200', 'time' => '22:00'],
        ];

        foreach ($cities as $city) {
            // Verifica si la ciudad ya existe antes de insertarla
            if (!City::where('code', $city['code'])->exists()) {
                City::create($city);
            }
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\City;

class MissingCitiesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $missingCities = [
            // San Luis
            ['extract_id' => 1, 'name' => 'SAN LUIS', 'code' => 'SLU1015', 'time' => '10:15'],
            ['extract_id' => 2, 'name' => 'SAN LUIS', 'code' => 'SLU1200', 'time' => '12:00'],
            ['extract_id' => 3, 'name' => 'SAN LUIS', 'code' => 'SLU1500', 'time' => '15:00'],
            ['extract_id' => 4, 'name' => 'SAN LUIS', 'code' => 'SLU1800', 'time' => '18:00'],
            ['extract_id' => 5, 'name' => 'SAN LUIS', 'code' => 'SLU2100', 'time' => '21:00'],
            
            // Chubut
            ['extract_id' => 1, 'name' => 'CHUBUT', 'code' => 'CHU1015', 'time' => '10:15'],
            ['extract_id' => 2, 'name' => 'CHUBUT', 'code' => 'CHU1200', 'time' => '12:00'],
            ['extract_id' => 3, 'name' => 'CHUBUT', 'code' => 'CHU1500', 'time' => '15:00'],
            ['extract_id' => 4, 'name' => 'CHUBUT', 'code' => 'CHU1800', 'time' => '18:00'],
            ['extract_id' => 5, 'name' => 'CHUBUT', 'code' => 'CHU2100', 'time' => '21:00'],
            
            // Formosa
            ['extract_id' => 1, 'name' => 'FORMOSA', 'code' => 'FOR1015', 'time' => '10:15'],
            ['extract_id' => 2, 'name' => 'FORMOSA', 'code' => 'FOR1200', 'time' => '12:00'],
            ['extract_id' => 3, 'name' => 'FORMOSA', 'code' => 'FOR1500', 'time' => '15:00'],
            ['extract_id' => 4, 'name' => 'FORMOSA', 'code' => 'FOR1800', 'time' => '18:00'],
            ['extract_id' => 5, 'name' => 'FORMOSA', 'code' => 'FOR2100', 'time' => '21:00'],
            
            // Catamarca
            ['extract_id' => 1, 'name' => 'CATAMARCA', 'code' => 'CAT1015', 'time' => '10:15'],
            ['extract_id' => 2, 'name' => 'CATAMARCA', 'code' => 'CAT1200', 'time' => '12:00'],
            ['extract_id' => 3, 'name' => 'CATAMARCA', 'code' => 'CAT1500', 'time' => '15:00'],
            ['extract_id' => 4, 'name' => 'CATAMARCA', 'code' => 'CAT1800', 'time' => '18:00'],
            ['extract_id' => 5, 'name' => 'CATAMARCA', 'code' => 'CAT2100', 'time' => '21:00'],
            
            // San Juan
            ['extract_id' => 1, 'name' => 'SAN JUAN', 'code' => 'SJU1015', 'time' => '10:15'],
            ['extract_id' => 2, 'name' => 'SAN JUAN', 'code' => 'SJU1200', 'time' => '12:00'],
            ['extract_id' => 3, 'name' => 'SAN JUAN', 'code' => 'SJU1500', 'time' => '15:00'],
            ['extract_id' => 4, 'name' => 'SAN JUAN', 'code' => 'SJU1800', 'time' => '18:00'],
            ['extract_id' => 5, 'name' => 'SAN JUAN', 'code' => 'SJU2100', 'time' => '21:00'],
        ];

        foreach ($missingCities as $cityData) {
            // Verificar si ya existe
            $existing = City::where('name', $cityData['name'])
                           ->where('extract_id', $cityData['extract_id'])
                           ->first();
            
            if (!$existing) {
                City::create($cityData);
                $this->command->info("Ciudad creada: {$cityData['name']} - Extract ID: {$cityData['extract_id']}");
            } else {
                $this->command->warn("Ciudad ya existe: {$cityData['name']} - Extract ID: {$cityData['extract_id']}");
            }
        }
        
        $this->command->info('Seeder de ciudades faltantes completado.');
    }
}

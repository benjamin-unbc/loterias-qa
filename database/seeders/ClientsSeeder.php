<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ClientsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear clientes de prueba
        $clients = [
            [
                'nombre' => 'Juan',
                'apellido' => 'Pérez',
                'correo' => 'juan.perez@ejemplo.com',
                'nombre_fantasia' => 'Juan Pérez Consultoría',
                'password' => 'Cliente123!',
                'is_active' => true,
            ],
            [
                'nombre' => 'María',
                'apellido' => 'González',
                'correo' => 'maria.gonzalez@empresa.com',
                'nombre_fantasia' => 'MG Soluciones',
                'password' => 'Cliente123!',
                'is_active' => true,
            ],
            [
                'nombre' => 'Carlos',
                'apellido' => 'Rodríguez',
                'correo' => 'carlos.rodriguez@negocio.com',
                'nombre_fantasia' => 'CR Servicios',
                'password' => 'Cliente123!',
                'is_active' => false,
            ],
            [
                'nombre' => 'Ana',
                'apellido' => 'Martínez',
                'correo' => 'ana.martinez@consultora.com',
                'nombre_fantasia' => 'AM Consultores',
                'password' => 'Cliente123!',
                'is_active' => true,
            ],
            [
                'nombre' => 'Luis',
                'apellido' => 'Fernández',
                'correo' => 'luis.fernandez@servicios.com',
                'nombre_fantasia' => 'LF Asociados',
                'password' => 'Cliente123!',
                'is_active' => true,
            ],
        ];

        foreach ($clients as $clientData) {
            Client::create($clientData);
        }
    }
}

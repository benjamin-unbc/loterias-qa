<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::create([
            'first_name' => 'admin',
            'last_name' => 'admin',
            'rut' => '14.079.973-4',
            'email' => 'admin@admin.com',
            'password' => bcrypt('Admin24!'),
            'phone' => '2613730359',
            'is_active' => true
        ]);
        $user->assignRole('Administrador');

        $user = User::create([
            'first_name' => 'Paola',
            'last_name' => 'Soto',
            'rut' => '11.912.690-8',
            'email' => 'psoto@unbcorp.cl',
            'password' => bcrypt('Paola24!'),
            'phone' => '2613730359',
            'is_active' => true
        ]);
        $user->assignRole('Administrador');

        $user = User::create([
            'first_name' => 'Usuario',
            'last_name' => 'Prueba',
            'rut' => '11.872.886-6',
            'email' => 'test@unbcorp.cl',
            'password' => bcrypt('Test24!'),
            'phone' => '2613730359',
            'is_active' => true
        ]);
        $user->assignRole('Basico');
    }
}

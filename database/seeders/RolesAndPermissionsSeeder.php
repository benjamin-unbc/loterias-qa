<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', [
            // Permisos antiguos que podrías querer eliminar si ya no son relevantes
            'crear permisos', 'editar permisos', 'ver permisos', 'eliminar permisos',
            'crear proyectos', 'editar proyectos', 'ver proyectos', 'eliminar proyectos',
        ])->delete();

        // --- Permisos de Visibilidad del Menú Lateral ---
        // Asegúrate de que estos nombres coincidan con la lógica que uses para mostrar/ocultar elementos del menú
        Permission::firstOrCreate(['name' => 'access_menu_gestor_de_jugadas']);
        Permission::firstOrCreate(['name' => 'access_menu_pagar_premio']);
        Permission::firstOrCreate(['name' => 'access_menu_resultados']);
        Permission::firstOrCreate(['name' => 'access_menu_liquidaciones']);
        Permission::firstOrCreate(['name' => 'access_menu_tabla_pagos']);
        Permission::firstOrCreate(['name' => 'access_menu_extractos']);
        Permission::firstOrCreate(['name' => 'access_menu_asistencia_remota']);
        Permission::firstOrCreate(['name' => 'access_menu_usuarios_y_roles']); // O como lo nombres para el módulo de usuarios

        // create permissions roles
        Permission::firstOrCreate(['name' => 'crear roles']);
        Permission::firstOrCreate(['name' => 'editar roles']);
        Permission::firstOrCreate(['name' => 'ver roles']);
        Permission::firstOrCreate(['name' => 'eliminar roles']);

        // create permissions users
        Permission::firstOrCreate(['name' => 'crear usuarios']);
        Permission::firstOrCreate(['name' => 'editar usuarios']);
        Permission::firstOrCreate(['name' => 'ver usuarios']);
        Permission::firstOrCreate(['name' => 'eliminar usuarios']);

        // create permissions clients
        Permission::firstOrCreate(['name' => 'crear clientes']);
        Permission::firstOrCreate(['name' => 'editar clientes']);
        Permission::firstOrCreate(['name' => 'ver clientes']);
        Permission::firstOrCreate(['name' => 'eliminar clientes']);


        // --- Permisos de Acciones por Módulo (Ejemplos) ---
        // Gestor de Jugadas
        Permission::firstOrCreate(['name' => 'plays_view']);
        Permission::firstOrCreate(['name' => 'plays_create']);
        Permission::firstOrCreate(['name' => 'plays_edit']);
        Permission::firstOrCreate(['name' => 'plays_delete']);

        // Extractos
        Permission::firstOrCreate(['name' => 'extracts_view']);
        Permission::firstOrCreate(['name' => 'extracts_create']); // Si aplica
        Permission::firstOrCreate(['name' => 'extracts_edit']);
        Permission::firstOrCreate(['name' => 'extracts_delete']); // Si aplica
        // Agrega permisos para otros módulos (Liquidaciones, Pagos, etc.) aquí

        // create roles and assign created permissions
        $basicRole = Role::firstOrCreate(['name' => 'Básico']);
        // Retrieve all permissions that contain the word "ver"
        $viewPermissions = Permission::where('name', 'like', '%ver%')->get();
        // Assign permissions that contain "ver" to the 'Básico' role
        $basicRole->givePermissionTo($viewPermissions);

        // create admin with all permissions
        $role = Role::firstOrCreate(['name' => 'Administrador']);
        $role->givePermissionTo(Permission::all());

        // create client role for web guard (mismo que administradores)
        $clientRole = Role::firstOrCreate(['name' => 'Cliente']);
        // Los clientes tendrán permisos básicos de visualización
        $clientPermissions = [
            'access_menu_gestor_de_jugadas',
            'plays_view',
            'access_menu_resultados',
            'access_menu_extractos'
        ];
        
        foreach ($clientPermissions as $permissionName) {
            $permission = Permission::firstOrCreate(['name' => $permissionName]);
            $clientRole->givePermissionTo($permission);
        }
    }
}

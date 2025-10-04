<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        // Si la ruta es para clientes, redirigir al login de clientes
        if ($request->is('client/*')) {
            return route('client.login');
        }

        // Por defecto, redirigir al login de administradores
        return route('login');
    }
}

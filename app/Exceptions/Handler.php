<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $e)
    {
        // Si es un error 419 (TokenMismatchException), redirigir al login
        if ($e instanceof TokenMismatchException) {
            // Limpiar la sesión
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Si es una petición AJAX o Livewire, devolver JSON
            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'message' => 'Tu sesión ha expirado. Por favor, inicia sesión nuevamente.',
                    'redirect' => route('login')
                ], 419);
            }

            // Redirigir al login con un mensaje
            return redirect()->route('login')
                ->with('error', 'Tu sesión ha expirado. Por favor, inicia sesión nuevamente.');
        }

        return parent::render($request, $e);
    }
}

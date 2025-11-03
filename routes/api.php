<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

// Ruta para verificación automática de números ganadores
Route::post('/check-new-numbers', [\App\Http\Controllers\Api\AutoUpdateController::class, 'checkNewNumbers']);

// Endpoint para verificar si un correo pertenece a un cliente
Route::get('/check-client/{email}', function ($email) {
    $user = \App\Models\User::where('email', $email)->first();
    
    if ($user) {
        // Refrescar el modelo para asegurar que los roles estén cargados
        $user->refresh();
        
        // Verificar si tiene el rol "Cliente"
        if ($user->hasRole('Cliente')) {
            // Buscar el cliente correspondiente para obtener el nombre de fantasía
            $client = \App\Models\Client::where('correo', $email)->first();
            $displayName = $client ? $client->nombre_fantasia : $user->first_name;
            
            if ($user->profile_photo_path) {
                return response()->json([
                    'is_client' => true,
                    'has_photo' => true,
                    'photo_path' => $user->profile_photo_path,
                    'name' => $displayName
                ]);
            } else {
                return response()->json([
                    'is_client' => true,
                    'has_photo' => false,
                    'name' => $displayName
                ]);
            }
        }
    }
    
    return response()->json([
        'is_client' => false,
        'has_photo' => false
    ]);
})->name('api.check-client');
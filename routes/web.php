<?php

use App\Http\Controllers\ClientAuthController;
use App\Http\Controllers\ArticleExtractorController;
use App\Http\Controllers\HeadsExtractorController;
use App\Livewire\SharedTicket;
use App\Livewire\ArticleExtractorInterface;
use App\Livewire\HeadsExtractorInterface;
use App\Livewire\WinningNumbersInterface;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/ticket/{token}', SharedTicket::class)->name('shared-ticket');

// Interfaz del extractor de artículos
Route::get('/extractor', ArticleExtractorInterface::class)->name('extractor.interface');

// Interfaz del extractor de cabezas
Route::get('/cabezas', HeadsExtractorInterface::class)->name('heads.interface');

// 20 Ganadores
Route::get('/20-ganadores', WinningNumbersInterface::class)->name('winning-numbers.interface');


// Rutas para el extractor de artículos
Route::prefix('api/extractor')->group(function () {
    Route::post('/article', [ArticleExtractorController::class, 'extractArticle'])->name('extractor.article');
    Route::post('/text', [ArticleExtractorController::class, 'extractText'])->name('extractor.text');
    Route::post('/metadata', [ArticleExtractorController::class, 'extractMetadata'])->name('extractor.metadata');
    Route::post('/multiple', [ArticleExtractorController::class, 'extractMultiple'])->name('extractor.multiple');
});

// Rutas para el extractor de cabezas
Route::prefix('api/heads')->group(function () {
    Route::post('/extract', [HeadsExtractorController::class, 'extractHeads'])->name('heads.extract');
    Route::post('/numbers', [HeadsExtractorController::class, 'extractNumbers'])->name('heads.numbers');
    Route::post('/multiple', [HeadsExtractorController::class, 'extractMultiple'])->name('heads.multiple');
    Route::post('/stats', [HeadsExtractorController::class, 'getStats'])->name('heads.stats');
});

// Ruta para verificar resultados nuevos antes de entrar a Extractos
Route::post('/api/check-new-results', function () {
    if (!auth()->check() || !auth()->user()->hasRole('Administrador')) {
        return response()->json(['error' => 'No autorizado'], 403);
    }
    
    try {
        $todayDate = Carbon::today()->toDateString();
        
        // Verificar si hay números para hoy
        $existingCount = \App\Models\Number::where('date', $todayDate)->count();
        
        if ($existingCount > 0) {
            return response()->json([
                'hasNumbers' => true,
                'count' => $existingCount,
                'message' => "Ya existen {$existingCount} números ganadores para hoy"
            ]);
        } else {
            return response()->json([
                'hasNumbers' => false,
                'count' => 0,
                'message' => 'No hay números ganadores para hoy'
            ]);
        }
        
    } catch (\Exception $e) {
        \Log::error('Error verificando resultados nuevos: ' . $e->getMessage());
        return response()->json(['error' => 'Error interno'], 500);
    }
})->middleware('auth');

// Endpoint para verificar si un correo pertenece a un cliente
Route::get('/api/check-client/{email}', function ($email) {
    $user = \App\Models\User::where('email', $email)->first();
    
    if ($user && $user->hasRole('Cliente')) {
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
    
    return response()->json([
        'is_client' => false,
        'has_photo' => false
    ]);
})->name('api.check-client');

// Los clientes ahora usan el login normal y acceden al sistema principal
// con permisos limitados según su rol "Cliente"

<?php

use App\Livewire\Admin\Extracts;
use App\Livewire\Admin\Liquidations;
use App\Livewire\Admin\PayAward;
use App\Livewire\Admin\PaymentTable;
use App\Livewire\Admin\PlaysManager;
use App\Livewire\Admin\PlaysSent;
use App\Livewire\Admin\RemoteAssistance;
use Illuminate\Support\Facades\Route;
use App\Livewire\Admin\Roles\ShowRoles;
use App\Livewire\Admin\Roles\StoreRole;
use App\Livewire\Admin\Users\ShowUsers;
use App\Livewire\Admin\Users\StoreUser;
use App\Livewire\Admin\Results;

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'role'
])->group(function () {
    Route::get('/', PlaysManager::class)->name('plays-manager');
    Route::get('/plays-manager', PlaysManager::class)->name('plays-manager');
    Route::get('/plays-sent', PlaysSent::class)->name('plays-sent');
    Route::get('/pay-award', PayAward::class)->name('pay-award');
    Route::get('/results', Results::class)->name('results');
    Route::get('/liquidations', Liquidations::class)->name('liquidations');
    Route::get('/payment-table', PaymentTable::class)->name('payment-table');
    Route::get('/extracts', Extracts::class)->name('extracts');
    Route::get('/remote-assistance', RemoteAssistance::class)->name('remote-assistance');


    //Users Module
    Route::prefix('module-users')->group(function () {

        //Roles abm
        Route::group(['middleware' => ['permission:crear roles|editar roles|ver roles|eliminar roles']], function () {
            Route::get('/roles', ShowRoles::class)->name('users.roles.show');
            Route::get('/roles/store/{id?}', StoreRole::class)->name('users.roles.store')
                ->middleware('permission:editar roles|crear roles');
        });

        //Users abm
        Route::group(['middleware' => ['permission:crear usuarios|editar usuarios|ver usuarios|eliminar usuarios']], function () {
            Route::get('/show', ShowUsers::class)->name('users.show');
            Route::get('/store/{id?}', StoreUser::class)->name('users.store')
                ->middleware('permission:editar usuarios|crear usuarios');
        });

      
    });
});

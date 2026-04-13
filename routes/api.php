<?php

use App\Http\Controllers\Api\ConsultaCedulaController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/consulta/cedula/{cedula}', [ConsultaCedulaController::class, 'show'])
        ->where('cedula', '[0-9]+')
        ->name('api.consulta.cedula');
});

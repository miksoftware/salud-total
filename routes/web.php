<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ConsultaController;

// --- Auth ---
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// --- Authenticated routes ---
Route::middleware('auth')->group(function () {

    // Dashboard redirect based on role
    Route::get('/', function () {
        return auth()->user()->isAdmin()
            ? redirect()->route('admin.consultas')
            : redirect()->route('consultas.search');
    })->name('dashboard');

    // === ADMIN routes ===
    Route::middleware('role:admin')->group(function () {
        // Upload & process module
        Route::get('/admin/consultas', [ConsultaController::class, 'index'])->name('admin.consultas');
        Route::post('/upload', [ConsultaController::class, 'upload'])->name('consultas.upload');
        Route::post('/process/{id}', [ConsultaController::class, 'processNext'])->name('consultas.process');
        Route::get('/status/{id}', [ConsultaController::class, 'status'])->name('consultas.status');
        Route::get('/export/{id}', [ConsultaController::class, 'export'])->name('consultas.export');
        Route::post('/test-connection', [ConsultaController::class, 'testConnection'])->name('consultas.test');
        Route::post('/resume/{id}', [ConsultaController::class, 'resume'])->name('consultas.resume');
        Route::delete('/consulta/{id}', [ConsultaController::class, 'destroy'])->name('consultas.destroy');

        // Users CRUD
        Route::get('/usuarios', [UserController::class, 'index'])->name('users.index');
        Route::post('/usuarios', [UserController::class, 'store'])->name('users.store');
        Route::put('/usuarios/{id}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/usuarios/{id}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    // === CONSULTA routes (both roles) ===
    Route::get('/consultas', [ConsultaController::class, 'search'])->name('consultas.search');
    Route::get('/consultas/buscar', [ConsultaController::class, 'searchByCedula'])->name('consultas.searchByCedula');
    Route::get('/consultas/archivos', [ConsultaController::class, 'files'])->name('consultas.files');
    Route::get('/consultas/archivos/{id}/export', [ConsultaController::class, 'export'])->name('consultas.files.export');
});

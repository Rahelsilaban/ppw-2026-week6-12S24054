<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BukuApiController;
use App\Http\Controllers\Api\V1\GithubConsumerController;
use App\Http\Controllers\Api\V1\PeminjamanApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SIPUS-Del REST API v1 Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // ----------------------------------------------------
    // Autentikasi Publik
    // ----------------------------------------------------
    Route::post('/auth/login', [AuthController::class, 'login'])->name('api.v1.auth.login');

    // ----------------------------------------------------
    // Katalog Buku Publik & REST Consumers
    // ----------------------------------------------------
    Route::get('/bukus', [BukuApiController::class, 'index'])->name('api.v1.bukus.index');
    Route::get('/bukus/{id}', [BukuApiController::class, 'show'])->name('api.v1.bukus.show');
    Route::get('/bukus/{id}/referensi-eksternal', [BukuApiController::class, 'referensiEksternal'])->name('api.v1.bukus.referensi');
    Route::get('/github/repo', [GithubConsumerController::class, 'getRepo'])->name('api.v1.github.repo');

    // ----------------------------------------------------
    // Endpoint Privat (Memerlukan Bearer Token Sanctum)
    // ----------------------------------------------------
    Route::middleware('auth:sanctum')->group(function () {
        // Profil & Logout
        Route::get('/auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');

        // Manajemen Buku
        Route::post('/bukus', [BukuApiController::class, 'store'])->name('api.v1.bukus.store');
        Route::put('/bukus/{id}', [BukuApiController::class, 'update'])->name('api.v1.bukus.update');
        Route::patch('/bukus/{id}', [BukuApiController::class, 'patch'])->name('api.v1.bukus.patch');
        Route::delete('/bukus/{id}', [BukuApiController::class, 'destroy'])->name('api.v1.bukus.destroy');

        // Transaksi Peminjaman ACID
        Route::get('/peminjamans', [PeminjamanApiController::class, 'index'])->name('api.v1.peminjamans.index');
        Route::get('/peminjamans/{id}', [PeminjamanApiController::class, 'show'])->name('api.v1.peminjamans.show');
        Route::post('/peminjamans', [PeminjamanApiController::class, 'store'])->name('api.v1.peminjamans.store');
        Route::patch('/peminjamans/{id}/kembalikan', [PeminjamanApiController::class, 'kembalikan'])->name('api.v1.peminjamans.kembalikan');
    });
});

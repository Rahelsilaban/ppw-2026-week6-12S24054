<?php

use App\Http\Controllers\BukuController;
use App\Http\Controllers\KategoriController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('buku.index');
});

Route::resource('buku', BukuController::class);
Route::resource('kategori', KategoriController::class);

Route::get('/peminjaman', [\App\Http\Controllers\PeminjamanController::class, 'index'])->name('peminjaman.index');
Route::post('/buku/{id}/pinjam', [\App\Http\Controllers\PeminjamanController::class, 'pinjam'])->name('peminjaman.pinjam');
Route::post('/peminjaman/{id}/kembali', [\App\Http\Controllers\PeminjamanController::class, 'kembali'])->name('peminjaman.kembali');

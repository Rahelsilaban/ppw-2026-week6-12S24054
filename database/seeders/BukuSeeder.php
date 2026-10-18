<?php

namespace Database\Seeders;

use App\Models\Buku;
use App\Models\Kategori;
use Illuminate\Database\Seeder;

class BukuSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Kategori::orderBy('id')->get() as $kategori) {
            foreach (['Dasar-Dasar', 'Panduan Praktis', 'Konsep dan Penerapan', 'Studi Kasus'] as $awalan) {
                Buku::factory()->for($kategori)->create(['judul' => $awalan.' '.$kategori->nama_kategori, 'stok' => 3]);
            }
        }
        $bukus = Buku::orderBy('id')->take(2)->get();
        $bukus[0]->update(['stok' => 1]);
        $bukus[1]->update(['stok' => 2]);
    }
}

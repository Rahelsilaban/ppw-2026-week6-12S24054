<?php

namespace Database\Seeders;

use App\Models\Kategori;
use Illuminate\Database\Seeder;

class KategoriSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['PRG' => 'Pemrograman', 'BD' => 'Basis Data', 'JRG' => 'Jaringan', 'MTK' => 'Matematika', 'SI' => 'Sistem Informasi'] as $kode => $nama) {
            Kategori::create(['kode_kategori' => $kode, 'nama_kategori' => $nama]);
        }
    }
}

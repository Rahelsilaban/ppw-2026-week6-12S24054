<?php

namespace Database\Seeders;

use App\Models\Buku;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PeminjamanSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach (Buku::orderBy('id')->take(3)->lockForUpdate()->get() as $index => $buku) {
                $aktif = $index < 2;
                $buku->peminjamans()->create([
                    'kode_pinjam' => 'PJM-DEMO000'.($index + 1),
                    'nim_peminjam' => '12S2405'.($index + 1),
                    'nama_peminjam' => ['Elisabet', 'Daniel', 'Maria'][$index],
                    'tanggal_pinjam' => today()->subDays(3),
                    'tanggal_jatuh_tempo' => today()->addDays(4),
                    'tanggal_kembali' => $aktif ? null : today()->subDay(),
                    'status' => $aktif ? 'dipinjam' : 'dikembalikan',
                ]);
                if ($aktif) {
                    $buku->decrement('stok');
                }
            }
        });
    }
}

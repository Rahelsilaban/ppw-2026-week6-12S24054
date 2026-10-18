<?php

namespace Tests\Feature;

use App\Models\Buku;
use App\Models\Peminjaman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeminjamanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function pinjam(Buku $buku, string $nim = '12s24054')
    {
        return $this->post(route('peminjaman.pinjam', $buku), ['nim_peminjam' => $nim, 'nama_peminjam' => 'Elisabet']);
    }

    public function test_kegagalan_insert_mengembalikan_stok(): void
    {
        $buku = Buku::factory()->create(['stok' => 2]);
        $this->pinjam($buku)->assertSessionHas('success');
        $kode = Peminjaman::firstOrFail()->kode_pinjam;
        Peminjaman::creating(function ($peminjaman) use ($kode) {
            $peminjaman->kode_pinjam = $kode;
        });
        try {
            $this->pinjam($buku, '12S24999')->assertSessionHas('error', 'Peminjaman gagal karena gangguan database. Silakan coba lagi.');
            $this->assertSame(1, $buku->fresh()->stok);
            $this->assertDatabaseCount('peminjamans', 1);
        } finally {
            Peminjaman::flushEventListeners();
        }
    }

    public function test_id_tidak_ditemukan_menghasilkan_404(): void
    {
        $this->post(route('peminjaman.pinjam', 999), ['nim_peminjam' => '12S24054', 'nama_peminjam' => 'Elisabet'])->assertNotFound();
        $this->post(route('peminjaman.kembali', 999))->assertNotFound();
    }

    public function test_stok_nol_ditolak(): void
    {
        $buku = Buku::factory()->create(['stok' => 0]);
        $this->pinjam($buku)->assertSessionHas('error');
        $this->assertSame(0, $buku->fresh()->stok);
        $this->assertDatabaseCount('peminjamans', 0);
    }

    public function test_nim_ganda_ditolak_dan_pengembalian_hanya_sekali(): void
    {
        $buku = Buku::factory()->create(['stok' => 2]);
        $this->pinjam($buku, ' 12s24054 ')->assertSessionHas('success');
        $this->assertSame(1, $buku->fresh()->stok);
        $this->pinjam($buku, '12S24054')->assertSessionHas('error');
        $this->assertSame(1, $buku->fresh()->stok);
        $this->assertDatabaseCount('peminjamans', 1);
        $pinjam = Peminjaman::firstOrFail();
        $this->assertSame('12S24054', $pinjam->nim_peminjam);
        $this->assertTrue($pinjam->tanggal_jatuh_tempo->equalTo(today()->addDays(7)));
        $this->post(route('peminjaman.kembali', $pinjam))->assertSessionHas('success');
        $this->assertSame(2, $buku->fresh()->stok);
        $this->assertSame('dikembalikan', $pinjam->fresh()->status);
        $this->post(route('peminjaman.kembali', $pinjam))->assertSessionHas('error');
        $this->assertSame(2, $buku->fresh()->stok);
    }
}

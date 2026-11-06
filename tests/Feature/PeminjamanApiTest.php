<?php

namespace Tests\Feature;

use App\Models\Buku;
use App\Models\Kategori;
use App\Models\Peminjaman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeminjamanApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $token;

    protected Kategori $kategori;

    protected Buku $buku;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;
        $this->kategori = Kategori::create([
            'kode_kategori' => 'IF',
            'nama_kategori' => 'Informatika',
        ]);
        $this->buku = Buku::create([
            'isbn' => '9786028888888',
            'judul' => 'Pemrograman Web Berbasis Komponen',
            'penulis' => 'Dosen Web',
            'penerbit' => 'Del Press',
            'tahun_terbit' => 2024,
            'kategori_id' => $this->kategori->id,
            'stok' => 2,
        ]);
    }

    public function test_borrow_book_success_decrements_stock_and_returns_201_with_location(): void
    {
        $payload = [
            'buku_id' => $this->buku->id,
            'nim_peminjam' => '12S24054',
            'nama_peminjam' => 'Rahel Silaban',
            'durasi_hari' => 7,
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/peminjamans', $payload);

        $response->assertStatus(201)
            ->assertHeader('Location')
            ->assertJsonPath('data.status', 'dipinjam')
            ->assertJsonPath('data.nim_peminjam', '12S24054');

        // Stok berkurang 1: dari 2 menjadi 1
        $this->assertEquals(1, $this->buku->fresh()->stok);

        // HATEOAS: Karena status 'dipinjam', link kembalikan_buku harus tersedia
        $links = $response->json('data._links');
        $this->assertArrayHasKey('kembalikan_buku', $links);
    }

    public function test_borrow_book_rejected_with_422_when_stock_is_zero(): void
    {
        $this->buku->update(['stok' => 0]);

        $payload = [
            'buku_id' => $this->buku->id,
            'nim_peminjam' => '12S24054',
            'nama_peminjam' => 'Rahel Silaban',
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/peminjamans', $payload);

        $response->assertStatus(422)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJson([
                'title' => 'Unprocessable Content',
                'status' => 422,
            ]);

        $this->assertEquals(0, $this->buku->fresh()->stok);
    }

    public function test_borrow_book_rejected_with_409_when_same_student_has_active_loan(): void
    {
        // Peminjaman pertama
        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/peminjamans', [
                'buku_id' => $this->buku->id,
                'nim_peminjam' => '12S24054',
                'nama_peminjam' => 'Rahel Silaban',
            ])->assertStatus(201);

        $this->assertEquals(1, $this->buku->fresh()->stok);

        // Peminjaman kedua oleh NIM yang sama untuk buku yang sama -> harus 409 Conflict
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/peminjamans', [
                'buku_id' => $this->buku->id,
                'nim_peminjam' => '12S24054',
                'nama_peminjam' => 'Rahel Silaban',
            ]);

        $response->assertStatus(409)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJson([
                'title' => 'Conflict',
                'status' => 409,
            ]);

        // Stok tidak boleh berkurang lagi
        $this->assertEquals(1, $this->buku->fresh()->stok);
    }

    public function test_return_book_increments_stock_and_updates_status(): void
    {
        $peminjaman = Peminjaman::create([
            'kode_pinjam' => 'PJM-RET001',
            'buku_id' => $this->buku->id,
            'nim_peminjam' => '12S24054',
            'nama_peminjam' => 'Rahel Silaban',
            'tanggal_pinjam' => today()->subDays(3),
            'tanggal_jatuh_tempo' => today()->addDays(4),
            'status' => 'dipinjam',
        ]);
        $this->buku->update(['stok' => 1]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->patchJson('/api/v1/peminjamans/'.$peminjaman->id.'/kembalikan');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'dikembalikan');

        // Stok bertambah 1: dari 1 menjadi 2
        $this->assertEquals(2, $this->buku->fresh()->stok);
        $this->assertNotNull($peminjaman->fresh()->tanggal_kembali);

        // HATEOAS: Setelah dikembalikan, link kembalikan_buku harus hilang
        $links = $response->json('data._links');
        $this->assertArrayNotHasKey('kembalikan_buku', $links);
    }

    public function test_double_return_rejected_with_409_conflict(): void
    {
        $peminjaman = Peminjaman::create([
            'kode_pinjam' => 'PJM-RET002',
            'buku_id' => $this->buku->id,
            'nim_peminjam' => '12S24054',
            'nama_peminjam' => 'Rahel Silaban',
            'tanggal_pinjam' => today()->subDays(5),
            'tanggal_jatuh_tempo' => today()->addDays(2),
            'tanggal_kembali' => today()->subDay(),
            'status' => 'dikembalikan',
        ]);
        $this->buku->update(['stok' => 2]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->patchJson('/api/v1/peminjamans/'.$peminjaman->id.'/kembalikan');

        $response->assertStatus(409)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJson([
                'title' => 'Conflict',
                'status' => 409,
            ]);

        // Stok tetap 2
        $this->assertEquals(2, $this->buku->fresh()->stok);
    }
}


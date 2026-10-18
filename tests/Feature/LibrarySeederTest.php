<?php

namespace Tests\Feature;

use App\Models\Buku;
use App\Models\Kategori;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibrarySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_twenty_valid_books_across_five_categories(): void
    {
        $this->seed();

        $this->assertDatabaseCount('kategoris', 5);
        $this->assertDatabaseCount('bukus', 20);
        $this->assertDatabaseCount('peminjamans', 3);
        $this->assertDatabaseHas('users', ['email' => 'pustakawan@del.ac.id']);
        $this->assertDatabaseHas('bukus', ['stok' => 0]);
        $this->assertDatabaseHas('bukus', ['stok' => 1]);
        foreach (Buku::orderBy('id')->take(3)->withCount(['peminjamans as aktif' => fn ($query) => $query->where('status', 'dipinjam')])->get() as $index => $buku) {
            $this->assertEquals($index + 1, $buku->stok + $buku->aktif);
        }
        $this->assertSame(20, Buku::distinct()->count('isbn'));

        foreach (Kategori::withCount('bukus')->get() as $kategori) {
            $this->assertSame(4, $kategori->bukus_count);
        }

        foreach (Buku::with('kategori')->get() as $buku) {
            $this->assertMatchesRegularExpression('/^[0-9]{13}$/', $buku->isbn);
            $this->assertGreaterThanOrEqual(1990, $buku->tahun_terbit);
            $this->assertLessThanOrEqual(now()->year, $buku->tahun_terbit);
            $this->assertGreaterThanOrEqual(0, $buku->stok);
            $this->assertLessThanOrEqual(50, $buku->stok);
            $this->assertNotNull($buku->kategori);
            $this->assertStringContainsString($buku->kategori->nama_kategori, $buku->judul);
            $this->assertNotEmpty($buku->penulis);
            $this->assertNotEmpty($buku->penerbit);
            $this->assertNotEmpty($buku->sinopsis);
        }
    }

    public function test_book_factory_can_create_its_own_category(): void
    {
        $buku = Buku::factory()->create();

        $this->assertDatabaseCount('bukus', 1);
        $this->assertDatabaseCount('kategoris', 1);
        $this->assertInstanceOf(Kategori::class, $buku->kategori);
    }
}

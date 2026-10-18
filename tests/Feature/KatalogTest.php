<?php

namespace Tests\Feature;

use App\Models\Buku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_pencarian_penulis_tetap_dibatasi_kategori(): void
    {
        $cocok = Buku::factory()->create(['penulis' => 'Penulis Target']);
        Buku::factory()->create(['penulis' => 'Penulis Target']);
        $this->get(route('buku.index', ['search' => 'Target', 'kategori' => $cocok->kategori_id]))
            ->assertOk()->assertViewHas('bukus', fn ($bukus) => $bukus->total() === 1 && $bukus->first()->id === $cocok->id)
            ->assertViewHas('statistik', fn ($stats) => $stats['Total Judul Buku'] === 2);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Buku;
use App\Models\Kategori;
use App\Models\Peminjaman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BukuApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $token;

    protected Kategori $kategori;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;
        $this->kategori = Kategori::create([
            'kode_kategori' => 'IF',
            'nama_kategori' => 'Informatika',
        ]);
    }

    public function test_public_can_get_book_catalog_with_pagination_and_hateoas(): void
    {
        Buku::create([
            'isbn' => '9786020000011',
            'judul' => 'Struktur Data C++',
            'penulis' => 'Dosen Del',
            'penerbit' => 'Del Press',
            'tahun_terbit' => 2024,
            'kategori_id' => $this->kategori->id,
            'stok' => 5,
        ]);

        Buku::create([
            'isbn' => '9786020000022',
            'judul' => 'Arsitektur Komputer Habis',
            'penulis' => 'Penulis Lain',
            'penerbit' => 'Del Press',
            'tahun_terbit' => 2023,
            'kategori_id' => $this->kategori->id,
            'stok' => 0,
        ]);

        $response = $this->getJson('/api/v1/bukus');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'isbn', 'judul', 'penulis', 'penerbit', 'tahun_terbit',
                        'stok', 'sinopsis', 'kategori_id', 'kategori', '_links',
                    ],
                ],
                'links',
                'meta',
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);

        // Buku dengan stok > 0 memiliki link pinjam_buku
        $bukuAdaStok = collect($data)->firstWhere('stok', 5);
        $this->assertArrayHasKey('pinjam_buku', $bukuAdaStok['_links']);

        // Buku dengan stok 0 TIDAK memiliki link pinjam_buku (HATEOAS dinamis)
        $bukuStokNol = collect($data)->firstWhere('stok', 0);
        $this->assertArrayNotHasKey('pinjam_buku', $bukuStokNol['_links']);
    }

    public function test_catalog_search_filter_and_sorting(): void
    {
        $kategoriLain = Kategori::create(['kode_kategori' => 'SI', 'nama_kategori' => 'Sistem Informasi']);

        Buku::create([
            'isbn' => '9786021111111',
            'judul' => 'Algoritma Pemrograman Lanjut',
            'penulis' => 'Alexander',
            'penerbit' => 'Del Press',
            'tahun_terbit' => 2022,
            'kategori_id' => $this->kategori->id,
            'stok' => 4,
        ]);

        Buku::create([
            'isbn' => '9786022222222',
            'judul' => 'Basis Data Relasional',
            'penulis' => 'Bernadette',
            'penerbit' => 'IT Press',
            'tahun_terbit' => 2024,
            'kategori_id' => $kategoriLain->id,
            'stok' => 0,
        ]);

        // Search test
        $searchRes = $this->getJson('/api/v1/bukus?search=Algoritma');
        $searchRes->assertStatus(200);
        $this->assertCount(1, $searchRes->json('data'));
        $this->assertEquals('Algoritma Pemrograman Lanjut', $searchRes->json('data.0.judul'));

        // Filter kategori test
        $filterCatRes = $this->getJson('/api/v1/bukus?kategori_id='.$kategoriLain->id);
        $this->assertCount(1, $filterCatRes->json('data'));
        $this->assertEquals('Basis Data Relasional', $filterCatRes->json('data.0.judul'));

        // Filter tersedia=1 test
        $filterTersediaRes = $this->getJson('/api/v1/bukus?tersedia=1');
        $this->assertCount(1, $filterTersediaRes->json('data'));
        $this->assertEquals('Algoritma Pemrograman Lanjut', $filterTersediaRes->json('data.0.judul'));
    }

    public function test_get_book_detail_and_404_handling(): void
    {
        $buku = Buku::create([
            'isbn' => '9786023333333',
            'judul' => 'Jaringan Komputer',
            'penulis' => 'Charles',
            'penerbit' => 'Del Press',
            'tahun_terbit' => 2023,
            'kategori_id' => $this->kategori->id,
            'stok' => 3,
        ]);

        $response = $this->getJson('/api/v1/bukus/'.$buku->id);
        $response->assertStatus(200)
            ->assertJsonPath('data.judul', 'Jaringan Komputer')
            ->assertJsonPath('data.kategori.nama_kategori', 'Informatika');

        // Test 404 RFC 7807 Problem Details
        $missing = $this->getJson('/api/v1/bukus/99999');
        $missing->assertStatus(404)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJson([
                'title' => 'Not Found',
                'status' => 404,
            ]);
    }

    public function test_authenticated_user_can_create_book_with_201_and_location(): void
    {
        $payload = [
            'isbn' => '9786029999999',
            'judul' => 'Rekayasa Perangkat Lunak Modern',
            'penulis' => 'Dr. Arifin',
            'penerbit' => 'Informatika ITB',
            'tahun_terbit' => 2023,
            'kategori_id' => $this->kategori->id,
            'stok' => 10,
            'sinopsis' => 'Buku pegangan RPL.',
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/bukus', $payload);

        $response->assertStatus(201)
            ->assertHeader('Location')
            ->assertJsonPath('data.isbn', '9786029999999');

        $this->assertDatabaseHas('bukus', ['isbn' => '9786029999999']);
    }

    public function test_put_book_requires_full_replacement(): void
    {
        $buku = Buku::create([
            'isbn' => '9786024444444',
            'judul' => 'Judul Lama',
            'penulis' => 'Penulis Lama',
            'penerbit' => 'Penerbit Lama',
            'tahun_terbit' => 2020,
            'kategori_id' => $this->kategori->id,
            'stok' => 2,
        ]);

        // PUT tanpa menyertakan semua field wajib (hanya kirim judul) -> harus ditolak 422
        $badPut = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->putJson('/api/v1/bukus/'.$buku->id, ['judul' => 'Judul Baru']);

        $badPut->assertStatus(422)
            ->assertHeader('Content-Type', 'application/problem+json');

        // PUT lengkap -> berhasil
        $goodPut = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->putJson('/api/v1/bukus/'.$buku->id, [
                'isbn' => '9786024444444',
                'judul' => 'Judul Diperbarui Penuh',
                'penulis' => 'Penulis Baru Penuh',
                'penerbit' => 'Penerbit Baru Penuh',
                'tahun_terbit' => 2025,
                'kategori_id' => $this->kategori->id,
                'stok' => 8,
            ]);

        $goodPut->assertStatus(200)
            ->assertJsonPath('data.judul', 'Judul Diperbarui Penuh')
            ->assertJsonPath('data.stok', 8);

        $this->assertDatabaseHas('bukus', [
            'id' => $buku->id,
            'judul' => 'Judul Diperbarui Penuh',
            'stok' => 8,
        ]);
    }

    public function test_patch_book_supports_partial_update(): void
    {
        $buku = Buku::create([
            'isbn' => '9786025555555',
            'judul' => 'Machine Learning Dasar',
            'penulis' => 'Penulis ML',
            'penerbit' => 'Del Press',
            'tahun_terbit' => 2023,
            'kategori_id' => $this->kategori->id,
            'stok' => 5,
        ]);

        // PATCH hanya mengubah stok tanpa menyertakan judul/penulis/dll
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->patchJson('/api/v1/bukus/'.$buku->id, [
                'stok' => 12,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.stok', 12)
            ->assertJsonPath('data.judul', 'Machine Learning Dasar');

        $this->assertDatabaseHas('bukus', [
            'id' => $buku->id,
            'stok' => 12,
            'judul' => 'Machine Learning Dasar',
        ]);
    }

    public function test_delete_book_blocked_when_active_loan_exists_with_409(): void
    {
        $buku = Buku::create([
            'isbn' => '9786026666666',
            'judul' => 'Keamanan Jaringan',
            'penulis' => 'Security Expert',
            'penerbit' => 'Del Press',
            'tahun_terbit' => 2023,
            'kategori_id' => $this->kategori->id,
            'stok' => 1,
        ]);

        // Buat pinjaman aktif
        Peminjaman::create([
            'kode_pinjam' => 'PJM-TEST01',
            'buku_id' => $buku->id,
            'nim_peminjam' => '12S24054',
            'nama_peminjam' => 'Rahel Silaban',
            'tanggal_pinjam' => today(),
            'tanggal_jatuh_tempo' => today()->addDays(7),
            'status' => 'dipinjam',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->deleteJson('/api/v1/bukus/'.$buku->id);

        $response->assertStatus(409)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJson([
                'title' => 'Conflict',
                'status' => 409,
            ]);

        $this->assertDatabaseHas('bukus', ['id' => $buku->id]);
    }

    public function test_delete_book_succeeds_when_no_active_loans(): void
    {
        $buku = Buku::create([
            'isbn' => '9786027777777',
            'judul' => 'Sistem Operasi',
            'penulis' => 'OS Author',
            'penerbit' => 'Del Press',
            'tahun_terbit' => 2022,
            'kategori_id' => $this->kategori->id,
            'stok' => 2,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->deleteJson('/api/v1/bukus/'.$buku->id);

        $response->assertStatus(204);
        $this->assertDatabaseMissing('bukus', ['id' => $buku->id]);
    }

    public function test_openlibrary_consumer_with_mock(): void
    {
        $buku = Buku::create([
            'isbn' => '9780132350884',
            'judul' => 'Clean Code',
            'penulis' => 'Robert C. Martin',
            'penerbit' => 'Prentice Hall',
            'tahun_terbit' => 2008,
            'kategori_id' => $this->kategori->id,
            'stok' => 3,
        ]);

        // Mock sukses OpenLibrary
        Http::fake([
            'openlibrary.org/*' => Http::response([
                'ISBN:9780132350884' => [
                    'title' => 'Clean Code: A Handbook of Agile Software Craftsmanship',
                    'publishers' => [['name' => 'Prentice Hall']],
                    'publish_date' => 'August 1, 2008',
                    'number_of_pages' => 464,
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/v1/bukus/'.$buku->id.'/referensi-eksternal');

        $response->assertStatus(200)
            ->assertJson([
                'found' => true,
                'external_source' => 'openlibrary',
                'fallback_applied' => false,
            ])
            ->assertJsonPath('metadata.number_of_pages', 464);
    }

    public function test_openlibrary_consumer_graceful_fallback_when_unavailable(): void
    {
        $buku = Buku::create([
            'isbn' => '9780132350884',
            'judul' => 'Clean Code',
            'penulis' => 'Robert C. Martin',
            'penerbit' => 'Prentice Hall',
            'tahun_terbit' => 2008,
            'kategori_id' => $this->kategori->id,
            'stok' => 3,
        ]);

        // Mock failure / timeout fallback
        Http::fake([
            'openlibrary.org/*' => Http::response(null, 500),
        ]);

        $failResponse = $this->getJson('/api/v1/bukus/'.$buku->id.'/referensi-eksternal');
        $failResponse->assertStatus(200)
            ->assertJson([
                'found' => false,
                'fallback_applied' => true,
            ]);
    }
}

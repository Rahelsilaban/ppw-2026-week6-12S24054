<?php

namespace Tests\Feature;

use App\Models\Buku;
use App\Models\Kategori;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LibraryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function bookData(): array
    {
        return [
            'isbn' => '9781234567890',
            'judul' => 'Pemrograman Web',
            'penulis' => 'Penulis Uji',
            'penerbit' => 'Penerbit Uji',
            'tahun_terbit' => now()->year,
            'kategori_id' => Kategori::factory()->create()->id,
            'stok' => 5,
            'sinopsis' => null,
        ];
    }

    public function test_both_resources_have_seven_named_routes(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->getName() ?? '', 'buku.')
                || str_starts_with($route->getName() ?? '', 'kategori.'));

        $this->assertCount(14, $routes);

        foreach (['buku', 'kategori'] as $resource) {
            foreach (['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'] as $action) {
                $this->assertTrue(Route::has($resource.'.'.$action));
            }
        }
    }

    public function test_valid_book_can_be_created_updated_without_changing_isbn_and_deleted(): void
    {
        $data = $this->bookData();
        $this->post(route('buku.store'), $data)
            ->assertRedirect(route('buku.index'))->assertSessionHas('success');
        $this->assertDatabaseHas('bukus', $data);

        $buku = Buku::firstOrFail();
        $data['judul'] = 'Pemrograman Web Lanjutan';
        $this->put(route('buku.update', $buku), $data)
            ->assertRedirect(route('buku.index'))->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseHas('bukus', ['id' => $buku->id, 'judul' => $data['judul'], 'isbn' => $data['isbn']]);

        $this->delete(route('buku.destroy', $buku))
            ->assertRedirect(route('buku.index'))->assertSessionHas('success');
        $this->assertDatabaseMissing('bukus', ['id' => $buku->id]);
    }

    public function test_invalid_book_fields_are_rejected_on_store_and_update(): void
    {
        $data = $this->bookData();
        $buku = Buku::factory()->create(['kategori_id' => $data['kategori_id']]);
        $original = $buku->fresh()->getAttributes();
        $invalidValues = [
            'isbn' => ['123', 'ABCDEFGHIJKLM', '12345678901234'],
            'judul' => ['Web', '', str_repeat('a', 256)],
            'penulis' => ['', str_repeat('a', 151)],
            'penerbit' => ['', str_repeat('a', 151)],
            'tahun_terbit' => [1899, now()->year + 1, 'abc'],
            'kategori_id' => [999999, null],
            'stok' => [-1, 'abc', 1.5],
            'sinopsis' => [['invalid']],
        ];

        foreach ($invalidValues as $field => $values) {
            foreach ($values as $value) {
                $invalid = array_replace($data, [$field => $value]);
                $this->from(route('buku.create'))->post(route('buku.store'), $invalid)
                    ->assertRedirect(route('buku.create'))->assertSessionHasErrors($field);
                $this->from(route('buku.edit', $buku))->put(route('buku.update', $buku), $invalid)
                    ->assertRedirect(route('buku.edit', $buku))->assertSessionHasErrors($field);
            }
        }

        $this->assertDatabaseCount('bukus', 1);
        $this->assertSame($original, $buku->fresh()->getAttributes());
    }

    public function test_duplicate_isbn_is_rejected_on_store_and_update(): void
    {
        $data = $this->bookData();
        Buku::create($data);
        $other = Buku::factory()->create(['kategori_id' => $data['kategori_id']]);
        $originalIsbn = $other->isbn;

        $this->post(route('buku.store'), $data)->assertSessionHasErrors('isbn');
        $this->put(route('buku.update', $other), $data)->assertSessionHasErrors('isbn');
        $this->assertDatabaseCount('bukus', 2);
        $this->assertSame($originalIsbn, $other->fresh()->isbn);
    }

    public function test_category_can_be_created_updated_with_same_code_and_deleted_when_empty(): void
    {
        $data = ['kode_kategori' => 'PRG', 'nama_kategori' => 'Pemrograman'];
        $this->post(route('kategori.store'), $data)
            ->assertRedirect(route('kategori.index'))->assertSessionHas('success');
        $this->assertDatabaseHas('kategoris', $data);
        $kategori = Kategori::firstOrFail();
        $data['nama_kategori'] = 'Pemrograman Web';

        $this->put(route('kategori.update', $kategori), $data)
            ->assertRedirect(route('kategori.index'))->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseHas('kategoris', $data);
        $this->delete(route('kategori.destroy', $kategori))
            ->assertRedirect(route('kategori.index'))->assertSessionHas('success');
        $this->assertDatabaseMissing('kategoris', ['id' => $kategori->id]);
    }

    public function test_invalid_and_duplicate_category_fields_are_rejected(): void
    {
        $kategori = Kategori::factory()->create(['kode_kategori' => 'PRG']);
        $other = Kategori::factory()->create(['kode_kategori' => 'BD']);
        $data = ['kode_kategori' => 'BARU', 'nama_kategori' => 'Kategori Baru'];

        foreach (['kode_kategori' => ['', 'PRG', str_repeat('a', 11)], 'nama_kategori' => ['', str_repeat('a', 101)]] as $field => $values) {
            foreach ($values as $value) {
                $invalid = array_replace($data, [$field => $value]);
                $this->post(route('kategori.store'), $invalid)->assertSessionHasErrors($field);
                $this->put(route('kategori.update', $other), $invalid)->assertSessionHasErrors($field);
            }
        }

        $this->assertDatabaseCount('kategoris', 2);
        $this->assertSame('BD', $other->fresh()->kode_kategori);
        $this->assertSame('PRG', $kategori->fresh()->kode_kategori);
    }

    public function test_category_with_books_cannot_be_deleted(): void
    {
        $buku = Buku::factory()->create();
        $this->delete(route('kategori.destroy', $buku->kategori))
            ->assertRedirect(route('kategori.index'))
            ->assertSessionHas('error', 'Kategori tidak dapat dihapus karena masih memiliki buku.');
        $this->assertDatabaseHas('kategoris', ['id' => $buku->kategori_id]);
        $this->assertDatabaseHas('bukus', ['id' => $buku->id]);
    }

    public function test_missing_records_return_404_through_implicit_binding(): void
    {
        foreach (['buku', 'kategori'] as $resource) {
            $this->get(route($resource.'.show', 999999))->assertNotFound();
            $this->get(route($resource.'.edit', 999999))->assertNotFound();
            $this->put(route($resource.'.update', 999999), [])->assertNotFound();
            $this->delete(route($resource.'.destroy', 999999))->assertNotFound();
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\Buku;
use App\Models\Kategori;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_all_crud_pages_render_with_the_shared_layout(): void
    {
        $buku = Buku::factory()->create();

        foreach (['buku' => $buku, 'kategori' => $buku->kategori] as $resource => $model) {
            foreach (['index', 'create', 'show', 'edit'] as $action) {
                $parameters = in_array($action, ['show', 'edit']) ? $model : [];
                $response = $this->get(route($resource.'.'.$action, $parameters));
                $response->assertOk()->assertSee('SIPUS-Del')->assertSee('bootstrap@5.3.8')
                    ->assertSee('href="'.route('buku.index').'"', false)
                    ->assertSee('href="'.route('kategori.index').'"', false);
                $this->assertSame(1, substr_count($response->getContent(), '<!DOCTYPE html>'));
            }
        }
    }

    public function test_empty_lists_and_category_selection_show_helpful_messages(): void
    {
        $this->get(route('buku.index'))->assertOk()->assertSee('Belum ada buku.');
        $this->get(route('kategori.index'))->assertOk()->assertSee('Belum ada kategori.');
        $this->get(route('buku.create'))->assertOk()->assertSee('Tambahkan kategori terlebih dahulu');
    }

    public function test_both_lists_use_bootstrap_pagination(): void
    {
        $kategori = Kategori::factory()->create();
        Buku::factory()->count(11)->for($kategori)->create();
        Kategori::factory()->count(10)->create();

        foreach (['buku' => 'bukus', 'kategori' => 'kategoris'] as $resource => $variable) {
            $this->get(route($resource.'.index'))->assertOk()
                ->assertViewHas($variable, fn ($items) => $items->count() === 10 && $items->total() === 11)
                ->assertSee('class="pagination"', false)->assertSee('page=2', false);
            $this->get(route($resource.'.index', ['page' => 2]))->assertOk()
                ->assertViewHas($variable, fn ($items) => $items->count() === 1);
        }
    }

    public function test_forms_have_csrf_method_spoofing_and_delete_confirmation(): void
    {
        $buku = Buku::factory()->create();

        foreach (['buku' => $buku, 'kategori' => $buku->kategori] as $resource => $model) {
            $this->get(route($resource.'.create'))->assertOk()->assertSee('name="_token"', false);
            $this->get(route($resource.'.edit', $model))->assertOk()
                ->assertSee('name="_token"', false)->assertSee('name="_method" value="PUT"', false);
            foreach (['index', 'show'] as $action) {
                $this->get(route($resource.'.'.$action, $action === 'show' ? $model : []))->assertOk()
                    ->assertSee('name="_method" value="DELETE"', false)->assertSee('onsubmit="return confirm(', false);
            }
        }
    }

    public function test_book_validation_displays_errors_and_preserves_old_values(): void
    {
        $kategori = Kategori::factory()->create();
        $data = ['isbn' => '9781234567890', 'judul' => 'Web', 'penulis' => 'Penulis Uji',
            'penerbit' => 'Penerbit Uji', 'tahun_terbit' => 2020, 'kategori_id' => $kategori->id,
            'stok' => -1, 'sinopsis' => 'Sinopsis tetap tersimpan'];

        $this->followingRedirects()->from(route('buku.create'))->post(route('buku.store'), $data)
            ->assertOk()
            ->assertSee('value="Web"', false)->assertSee('value="-1"', false)
            ->assertSee('id="judul-error"', false)->assertSee('id="stok-error"', false)
            ->assertSee('Sinopsis tetap tersimpan')
            ->assertSee('value="'.$kategori->id.'" selected', false);
    }

    public function test_category_validation_preserves_input_and_edit_forms_show_existing_values(): void
    {
        $buku = Buku::factory()->create();
        $this->get(route('buku.edit', $buku))->assertOk()->assertSee('value="'.$buku->isbn.'"', false);
        $this->get(route('kategori.edit', $buku->kategori))->assertOk()->assertSee($buku->kategori->kode_kategori);
        $this->followingRedirects()->from(route('kategori.create'))
            ->post(route('kategori.store'), ['kode_kategori' => 'BARU', 'nama_kategori' => ''])->assertOk()
            ->assertSee('value="BARU"', false)->assertSee('id="nama_kategori-error"', false);
    }

    public function test_user_content_is_escaped_and_flash_messages_are_visible(): void
    {
        $unsafe = '<script>alert(1)</script>';
        $buku = Buku::factory()->create(['judul' => $unsafe, 'sinopsis' => $unsafe]);
        $buku->kategori->update(['nama_kategori' => $unsafe]);

        foreach (['buku' => $buku, 'kategori' => $buku->kategori] as $resource => $model) {
            foreach (['index', 'show'] as $action) {
                $this->get(route($resource.'.'.$action, $action === 'show' ? $model : []))
                    ->assertOk()->assertSee($unsafe)->assertDontSee($unsafe, false);
            }
        }

        $this->followingRedirects()->delete(route('kategori.destroy', $buku->kategori))
            ->assertOk()->assertSee('alert-danger')->assertSee('Kategori tidak dapat dihapus karena masih memiliki buku.');
        $this->followingRedirects()->delete(route('buku.destroy', $buku))
            ->assertOk()->assertSee('alert-success')->assertSee('Buku berhasil dihapus.');
    }
}

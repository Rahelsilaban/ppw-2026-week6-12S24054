<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_semua_rute_mutasi_memerlukan_login(): void
    {
        foreach (['buku', 'kategori'] as $resource) {
            foreach (['create' => 'get', 'store' => 'post', 'edit' => 'get', 'update' => 'put', 'destroy' => 'delete'] as $action => $method) {
                $this->$method(route($resource.'.'.$action, in_array($action, ['edit', 'update', 'destroy']) ? 999 : []))->assertRedirect(route('login'));
            }
            $this->patch(route($resource.'.update', 999))->assertRedirect(route('login'));
        }
        $this->get(route('peminjaman.index'))->assertRedirect(route('login'));
        $this->post(route('peminjaman.pinjam', 999))->assertRedirect(route('login'));
        $this->post(route('peminjaman.kembali', 999))->assertRedirect(route('login'));
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->get(route('buku.index'))->assertOk();
    }

    public function test_login_cookie_sesi_dan_logout(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);
        $this->get(route('login'))->assertOk();
        $oldId = session()->getId();
        $response = $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password123', 'remember' => 1]);
        $response->assertRedirect(route('buku.index'))->assertSessionHas('login_timestamp')->assertCookie('del_last_user_email', $user->email);
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldId, session()->getId());
        $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === 'del_last_user_email');
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, $cookie->getExpiresTime(), 5);
        $this->get(route('buku.index', ['search' => 'Basis']))->assertSessionHas('last_search', 'Basis');
        $this->get(route('buku.index'))->assertSee('value="Basis"', false);
        $token = session()->token();
        $this->post(route('logout'))->assertRedirect(route('login'))->assertSessionMissing('login_timestamp')->assertSessionMissing('last_search');
        $this->assertGuest();
        $this->assertNotSame($token, session()->token());
    }

    public function test_login_salah_tidak_menyimpan_password(): void
    {
        $this->post(route('login.store'), ['email' => 'salah@del.ac.id', 'password' => 'salah'])
            ->assertSessionHas('error')->assertSessionMissing('_old_input.password');
        $this->assertGuest();
    }
}

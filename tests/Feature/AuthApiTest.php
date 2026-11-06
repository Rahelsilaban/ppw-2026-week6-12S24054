<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_librarian_can_login_and_receive_bearer_token(): void
    {
        $user = User::factory()->create([
            'email' => 'pustakawan@del.ac.id',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'pustakawan@del.ac.id',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'token_type',
                'access_token',
                'user' => ['id', 'name', 'email'],
            ]);

        $this->assertEquals('Bearer', $response->json('token_type'));
        $this->assertNotEmpty($response->json('access_token'));
    }

    public function test_login_with_invalid_credentials_returns_rfc7807_unauthorized(): void
    {
        User::factory()->create([
            'email' => 'pustakawan@del.ac.id',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'pustakawan@del.ac.id',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJson([
                'type' => 'https://tools.ietf.org/html/rfc7235#section-3.1',
                'title' => 'Unauthorized',
                'status' => 401,
            ]);
    }

    public function test_authenticated_user_can_access_me_profile(): void
    {
        $user = User::factory()->create(['name' => 'Librarian Del']);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => 'Librarian Del',
                    'email' => $user->email,
                ],
            ]);
    }

    public function test_unauthenticated_request_to_me_returns_rfc7807_unauthorized(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJson([
                'title' => 'Unauthorized',
                'status' => 401,
            ]);
    }

    public function test_librarian_can_logout_and_token_is_revoked(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $logoutResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $logoutResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        // Reset auth guard cache untuk memverifikasi token yang sudah dihapus dari database
        $this->app['auth']->forgetGuards();

        // Request berikutnya dengan token yang sudah dicabut harus ditolak 401
        $meResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $meResponse->assertStatus(401);
    }
}

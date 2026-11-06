<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GithubConsumerTest extends TestCase
{
    public function test_github_consumer_fetches_repo_and_masks_token(): void
    {
        Http::fake([
            'api.github.com/repos/*' => Http::response([
                'name' => 'ppw-2026-week6-12S24054',
                'full_name' => 'Rahelsilaban/ppw-2026-week6-12S24054',
                'owner' => ['login' => 'Rahelsilaban'],
                'html_url' => 'https://github.com/Rahelsilaban/ppw-2026-week6-12S24054',
                'description' => 'SIPUS-Del Repository',
                'default_branch' => 'main',
                'visibility' => 'public',
            ], 200),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ghp_sampleSecretTokenForTestingOnly1234')
            ->getJson('/api/v1/github/repo');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'source' => 'GitHub REST API v3',
                'authenticated' => true,
                'repository' => [
                    'name' => 'ppw-2026-week6-12S24054',
                    'owner' => 'Rahelsilaban',
                ],
            ]);

        // Pastikan token disensor (tidak bocor secara plaintext)
        $tokenUsed = $response->json('token_used');
        $this->assertStringStartsWith('ghp_', $tokenUsed);
        $this->assertStringContainsString('****', $tokenUsed);
        $this->assertStringNotContainsString('sampleSecretTokenForTestingOnly1234', $tokenUsed);
    }

    public function test_github_consumer_handles_offline_gracefully(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response(null, 500),
        ]);

        $response = $this->getJson('/api/v1/github/repo');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'repository' => [
                    'name' => 'ppw-2026-week6-12S24054',
                ],
            ]);
    }
}

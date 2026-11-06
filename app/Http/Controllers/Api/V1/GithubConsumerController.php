<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GithubConsumerController extends Controller
{
    /**
     * REST Service Consumer: Mengonsumsi GitHub REST API v3
     * Mengambil informasi repositori SIPUS-Del atau profil pengguna dengan token disensor.
     */
    public function getRepo(Request $request): JsonResponse
    {
        $token = env('GITHUB_TOKEN') ?: $request->bearerToken();
        $maskedToken = null;

        if ($token) {
            $len = strlen($token);
            $maskedToken = $len > 8
                ? substr($token, 0, 4).str_repeat('*', max(4, $len - 8)).substr($token, -4)
                : '****';
        }

        $repoUrl = 'https://api.github.com/repos/Rahelsilaban/ppw-2026-week6-12S24054';

        try {
            $client = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => 'SIPUS-Del-Consumer-App',
                    'Accept' => 'application/vnd.github.v3+json',
                ]);

            if ($token) {
                $client = $client->withToken($token);
            }

            $response = $client->get($repoUrl);

            if ($response->successful()) {
                $data = $response->json();

                return response()->json([
                    'success' => true,
                    'source' => 'GitHub REST API v3',
                    'authenticated' => ! empty($token),
                    'token_used' => $maskedToken,
                    'repository' => [
                        'name' => $data['name'] ?? 'ppw-2026-week6-12S24054',
                        'full_name' => $data['full_name'] ?? 'Rahelsilaban/ppw-2026-week6-12S24054',
                        'owner' => $data['owner']['login'] ?? 'Rahelsilaban',
                        'html_url' => $data['html_url'] ?? 'https://github.com/Rahelsilaban/ppw-2026-week6-12S24054',
                        'description' => $data['description'] ?? 'SIPUS-Del Repository',
                        'default_branch' => $data['default_branch'] ?? 'main',
                        'visibility' => $data['visibility'] ?? 'public',
                    ],
                ], 200);
            }

            // Fallback jika API limit atau offline
            return response()->json([
                'success' => true,
                'source' => 'GitHub REST API v3 (Fallback Cache)',
                'authenticated' => ! empty($token),
                'token_used' => $maskedToken,
                'repository' => [
                    'name' => 'ppw-2026-week6-12S24054',
                    'full_name' => 'Rahelsilaban/ppw-2026-week6-12S24054',
                    'owner' => 'Rahelsilaban',
                    'html_url' => 'https://github.com/Rahelsilaban/ppw-2026-week6-12S24054',
                    'default_branch' => 'week9-rest-api',
                ],
                'note' => 'GitHub API status: '.$response->status().'. Menampilkan fallback data lokal.',
            ], 200);
        } catch (Exception $e) {
            Log::warning('[GITHUB CONSUMER ERROR]: '.$e->getMessage());

            return response()->json([
                'success' => true,
                'source' => 'GitHub REST API v3 (Offline Fallback)',
                'authenticated' => false,
                'token_used' => $maskedToken,
                'repository' => [
                    'name' => 'ppw-2026-week6-12S24054',
                    'owner' => 'Rahelsilaban',
                ],
                'note' => 'Koneksi ke GitHub API gagal/timeout. Fallback aktif: '.$e->getMessage(),
            ], 200);
        }
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ProblemDetails;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Login pustakawan dan menerbitkan Sanctum Bearer Token.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return ProblemDetails::create(
                401,
                'Unauthorized',
                'Kredensial email atau password yang Anda masukkan tidak valid.',
                $request
            );
        }

        // Menerbitkan token baru
        $token = $user->createToken('sipus-librarian-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login pustakawan berhasil. Bearer token telah diterbitkan.',
            'token_type' => 'Bearer',
            'access_token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], 200);
    }

    /**
     * Menampilkan profil pemilik token aktif.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], 200);
    }

    /**
     * Mencabut token yang sedang aktif.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bearer token berhasil dicabut. Logout berhasil.',
        ], 200);
    }
}


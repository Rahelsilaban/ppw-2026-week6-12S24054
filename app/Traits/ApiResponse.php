<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

trait ApiResponse
{
    /**
     * Mengembalikan respons sukses standar JSON.
     */
    protected function successResponse(mixed $data = null, string $message = 'Sukses', int $statusCode = 200, array $headers = []): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        return response()->json($payload, $statusCode, $headers);
    }

    /**
     * Mengembalikan respons 201 Created dengan header Location.
     */
    protected function createdResponse(mixed $data, string $message = 'Data berhasil dibuat', ?string $location = null): JsonResponse
    {
        $headers = [];
        if ($location) {
            $headers['Location'] = $location;
        }

        return $this->successResponse($data, $message, 201, $headers);
    }

    /**
     * Mengembalikan respons 204 No Content.
     */
    protected function noContentResponse(): Response
    {
        return response()->noContent();
    }

    /**
     * Mengembalikan respons galat terstruktur jika tidak ditangani oleh RFC 7807 handler.
     */
    protected function errorResponse(string $message, int $statusCode = 400, mixed $errors = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $statusCode);
    }
}


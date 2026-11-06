<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ProblemDetails
{
    /**
     * Membentuk respons RFC 7807 Problem Details.
     */
    public static function fromThrowable(Throwable $e, Request $request): JsonResponse
    {
        $status = 500;
        $title = 'Internal Server Error';
        $type = 'https://tools.ietf.org/html/rfc7231#section-6.6.1';
        $detail = config('app.debug') ? $e->getMessage() : 'Terjadi kesalahan internal pada server.';
        $extra = [];

        if ($e instanceof AuthenticationException) {
            $status = 401;
            $title = 'Unauthorized';
            $type = 'https://tools.ietf.org/html/rfc7235#section-3.1';
            $detail = 'Autentikasi Bearer Token diperlukan atau token yang disertakan tidak valid.';
        } elseif ($e instanceof ModelNotFoundException) {
            $status = 404;
            $title = 'Not Found';
            $type = 'https://tools.ietf.org/html/rfc7231#section-6.5.4';
            $detail = 'Data sumber daya yang diminta tidak ditemukan.';
        } elseif ($e instanceof ValidationException) {
            $status = 422;
            $title = 'Unprocessable Content';
            $type = 'https://tools.ietf.org/html/rfc4918#section-11.2';
            $detail = $e->getMessage() ?: 'Validasi atribut request gagal.';
            $extra['invalid_params'] = $e->errors();
        } elseif ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $title = match ($status) {
                400 => 'Bad Request',
                401 => 'Unauthorized',
                403 => 'Forbidden',
                404 => 'Not Found',
                405 => 'Method Not Allowed',
                409 => 'Conflict',
                422 => 'Unprocessable Content',
                default => 'HTTP Error',
            };
            $type = match ($status) {
                404 => 'https://tools.ietf.org/html/rfc7231#section-6.5.4',
                409 => 'https://tools.ietf.org/html/rfc7231#section-6.5.8',
                422 => 'https://tools.ietf.org/html/rfc4918#section-11.2',
                default => 'https://tools.ietf.org/html/rfc7231',
            };
            $detail = $e->getMessage() ?: $title;
        }

        $payload = array_merge([
            'type' => $type,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'instance' => $request->getRequestUri(),
        ], $extra);

        return response()->json($payload, $status, [
            'Content-Type' => 'application/problem+json',
        ]);
    }

    /**
     * Membentuk respons error kustom berformat RFC 7807 (misalnya untuk Conflict atau Business Rule).
     */
    public static function create(int $status, string $title, string $detail, Request $request, ?string $type = null, array $extra = []): JsonResponse
    {
        $defaultType = match ($status) {
            400 => 'https://tools.ietf.org/html/rfc7231#section-6.5.1',
            401 => 'https://tools.ietf.org/html/rfc7235#section-3.1',
            403 => 'https://tools.ietf.org/html/rfc7231#section-6.5.3',
            404 => 'https://tools.ietf.org/html/rfc7231#section-6.5.4',
            409 => 'https://tools.ietf.org/html/rfc7231#section-6.5.8',
            422 => 'https://tools.ietf.org/html/rfc4918#section-11.2',
            default => 'https://tools.ietf.org/html/rfc7231#section-6.6.1',
        };

        $payload = array_merge([
            'type' => $type ?: $defaultType,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'instance' => $request->getRequestUri(),
        ], $extra);

        return response()->json($payload, $status, [
            'Content-Type' => 'application/problem+json',
        ]);
    }
}

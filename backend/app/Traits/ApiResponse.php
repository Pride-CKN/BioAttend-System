<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Format respons seragam seluruh endpoint (PRD 18.1):
 *   sukses : { success: true,  message, data, meta }
 *   gagal  : { success: false, message, errors }
 */
trait ApiResponse
{
    protected function success(mixed $data = null, string $message = 'Berhasil', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function created(mixed $data = null, string $message = 'Data berhasil dibuat'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function accepted(mixed $data = null, string $message = 'Permintaan diterima dan sedang diproses'): JsonResponse
    {
        return $this->success($data, $message, 202);
    }

    /** Respons sukses lengkap dengan meta pagination. */
    protected function paginated(LengthAwarePaginator $paginator, string $message = 'Berhasil'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $paginator->items(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    protected function error(string $message, int $status = 400, mixed $errors = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    protected function notFound(string $message = 'Data tidak ditemukan'): JsonResponse
    {
        return $this->error($message, 404);
    }

    protected function forbidden(string $message = 'Anda tidak memiliki hak akses'): JsonResponse
    {
        return $this->error($message, 403);
    }

    protected function unprocessable(string $message, mixed $errors = null): JsonResponse
    {
        return $this->error($message, 422, $errors);
    }

    protected function conflict(string $message): JsonResponse
    {
        return $this->error($message, 409);
    }
}

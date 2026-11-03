<?php

use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\RequestContext;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Konteks request & header keamanan berlaku untuk SELURUH endpoint API
        // (termasuk /health dan fallback 404), bukan hanya route terautentikasi.
        $middleware->api(append: [
            RequestContext::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'role' => EnsureRole::class,
            'password.changed' => EnsurePasswordChanged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // 422 — validasi gagal
        $exceptions->render(function (ValidationException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'Data yang dikirim tidak valid.',
                'errors' => $e->errors(),
            ], 422);
        });

        // 401 — belum terotentikasi / token tidak valid
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak terotentikasi. Sertakan token Bearer yang valid.',
            ], 401);
        });

        // 403 — role/scope tidak diizinkan
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Anda tidak memiliki hak akses untuk tindakan ini.',
            ], 403);
        });

        // 404 — resource tidak ditemukan
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan.',
            ], 404);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Endpoint tidak ditemukan.',
                ], 404);
            }

            return null;
        });

        // 429 — rate limit terlampaui
        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'Terlalu banyak permintaan. Coba lagi beberapa saat.',
            ], 429);
        });

        // Tangkapan terakhir: HttpException lain (401/403/404/419/429/5xx)
        // yang dilempar lewat abort() di middleware maupun controller.
        $exceptions->render(function (HttpException $e, Request $request) {
            $status = $e->getStatusCode();

            if ($status >= 500 && !config('app.debug')) {
                // Jangan bocorkan detail internal; respond() di bawah akan
                // membungkusnya dengan kontrak error yang seragam.
                return response()->json([
                    'success' => false,
                    'message' => 'Terjadi kesalahan pada server.',
                ], $status);
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: (Response::$statusTexts[$status] ?? 'Terjadi kesalahan.'),
            ], $status);
        });

        // Normalisasi akhir: SELURUH respons error pada /api/* memuat kontrak
        // { success:false, message, error:{code,message,details}, request_id }.
        // Field `errors` lama tetap dipertahankan untuk kompatibilitas klien.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if (!$request->is('api/*') || $status < 400) {
                return $response;
            }

            $decoded = json_decode($response->getContent(), true);
            $data = is_array($decoded) ? $decoded : [];

            if ($status >= 500 && !config('app.debug')) {
                $data = array_diff_key($data, array_flip([
                    'exception',
                    'file',
                    'line',
                    'trace',
                    'errors',
                    'message',
                ]));
                $data['message'] = 'Terjadi kesalahan pada server.';
            }

            $message = $data['message'] ?? (Response::$statusTexts[$status] ?? 'Terjadi kesalahan.');
            $details = $data['errors'] ?? ($data['error']['details'] ?? null);

            $data['success'] = false;
            $data['message'] = $message;
            $data['request_id'] = app()->bound('request_id')
                ? app('request_id')
                : bin2hex(random_bytes(16));
            $data['error'] = $data['error'] ?? [
                'code' => match (true) {
                    $status === 401 => 'UNAUTHENTICATED',
                    $status === 403 => 'FORBIDDEN',
                    $status === 404 => 'NOT_FOUND',
                    $status === 409 => 'CONFLICT',
                    $status === 422 => 'VALIDATION_FAILED',
                    $status === 428 => 'PASSWORD_CHANGE_REQUIRED',
                    $status === 429 => 'RATE_LIMIT_EXCEEDED',
                    $status >= 500 => 'INTERNAL_ERROR',
                    default => 'HTTP_ERROR',
                },
                'message' => $message,
                'details' => $details,
            ];

            $json = response()->json($data, $status);

            if ($response->headers->has('Retry-After')) {
                $json->headers->set('Retry-After', $response->headers->get('Retry-After'));
            }

            return $json;
        });
    })->create();

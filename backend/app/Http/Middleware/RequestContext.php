<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menetapkan X-Request-ID untuk setiap permintaan API observability
 *
 * ID dipakai di: response header, kontrak error, dan kolom audit_logs.request_id
 * sehingga satu insiden bisa ditelusuri lintas log, queue, dan audit trail.
 * Header dari klien hanya diterima bila bentuknya aman (mencegah log injection).
 */
class RequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = (string) $request->header('X-Request-ID', '');
        $id = preg_match('/^[A-Za-z0-9._:-]{8,64}$/', $incoming)
            ? $incoming
            : (string) Str::uuid();

        $request->headers->set('X-Request-ID', $id);
        app()->instance('request_id', $id);

        $response = $next($request);
        $response->headers->set('X-Request-ID', $id);

        return $response;
    }
}

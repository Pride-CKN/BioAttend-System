<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/* Pemeriksaan role untuk endpoint
  Pemakaian: ->middleware('role:HRD') atau ->middleware('role:HRD,KADIV') */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Tidak terotentikasi.');
        }

        if (! $user->is_active) {
            abort(403, 'Akun tidak aktif.');
        }

        if ($roles === [] || $roles === ['']) {
            return $next($request);
        }

        if (! $user->hasRole(...$roles)) {
            abort(403, 'Role Anda tidak diizinkan mengakses endpoint ini.');
        }

        return $next($request);
    }
}

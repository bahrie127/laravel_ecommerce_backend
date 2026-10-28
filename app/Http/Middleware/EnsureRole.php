<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi rute per peran (`users.roles`: `seller` atau `user` = pembeli). Dulu semua rute seller/buyer
 * hanya butuh login, sehingga pembeli bisa menambah produk dan penjual bisa membuat order.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! in_array($request->user()?->roles, $roles, true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akun Anda tidak memiliki akses ke fitur ini.',
            ], 403);
        }

        return $next($request);
    }
}

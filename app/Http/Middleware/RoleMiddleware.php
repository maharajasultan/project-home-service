<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Pemakaian di route: ->middleware('role:user') atau ->middleware('role:user,technician')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Tidak terautentikasi.'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Akun Anda dinonaktifkan. Hubungi admin.'], 403);
        }

        if (! in_array($user->role->value, $roles, true)) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke fitur ini.'], 403);
        }

        return $next($request);
    }
}
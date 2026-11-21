<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi rute berdasarkan kemampuan (gate) peran pengguna, mis. permission:manage-master (FR-22).
 */
class EnsurePermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $user = $request->user();

        abort_unless($user && $user->is_active && $user->canAny($abilities), 403, 'Anda tidak memiliki akses ke halaman ini.');

        return $next($request);
    }
}

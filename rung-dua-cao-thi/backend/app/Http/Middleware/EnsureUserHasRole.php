<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowedRoles = array_map(
            static fn (string $role): string => strtoupper($role),
            $roles,
        );

        if (! in_array($request->user()?->role?->value, $allowedRoles, true)) {
            abort(403, 'Bạn không có quyền thực hiện thao tác này.');
        }

        return $next($request);
    }
}

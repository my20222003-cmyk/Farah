<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403, 'Unauthorized admin access.');
        }

        $authorizedRoles = $roles ?: ['ops.moderator', 'finance.reviewer', 'system.admin'];

        if (! $user->hasAnyRole($authorizedRoles)) {
            abort(403, 'This admin role is not authorized for this section.');
        }

        return $next($request);
    }
}

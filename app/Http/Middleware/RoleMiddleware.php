<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $normalized = collect($roles)
            ->flatMap(fn (string $role) => explode('|', $role))
            ->map(fn (string $role) => trim($role))
            ->filter()
            ->values()
            ->all();

        if (! $user->hasAnyRole($normalized)) {
            abort(403, 'لا تملك صلاحية الوصول إلى هذه الصفحة.');
        }

        return $next($request);
    }
}

<?php

namespace Modules\Agent\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAgentRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = auth('agent')->user();

        if (!$user || !in_array($user->role, $roles, true)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return $next($request);
    }
}

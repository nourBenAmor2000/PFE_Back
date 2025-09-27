<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAgentRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
   
        public function handle($request, Closure $next, ...$roles)
    {
        $agent = auth()->guard('agent')->user();

        if (!$agent || !in_array($agent->role, $roles)) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        return $next($request);
    }

}

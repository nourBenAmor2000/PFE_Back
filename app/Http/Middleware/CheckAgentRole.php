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

        if (!$agent) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        // Normalize user role to match frontend naming convention
        $userRole = $this->normalizeRole($agent->role);
        
        // Normalize required roles and check
        $normalizedRoles = array_map([$this, 'normalizeRole'], $roles);
        
        if (!in_array($userRole, $normalizedRoles)) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        return $next($request);
    }

    /**
     * Normalize role names to match frontend convention
     */
    protected function normalizeRole(string $role): string
    {
        $r = strtolower(trim($role));
        return match ($r) {
            'admin_agence', 'adminagence', 'agency_admin', 'admin_agent' => 'admin_agence',
            'agent_rh', 'rh', 'agentrh' => 'agent_rh',
            'agent_personnel', 'agentpersonnel', 'agent', 'personnel' => 'agent_personnel',
            default => $r,
        };
    }

}

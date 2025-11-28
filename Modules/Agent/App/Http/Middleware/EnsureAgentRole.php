<?php

namespace Modules\Agent\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAgentRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = auth('agent')->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Normalize user role to match frontend naming convention
        $userRole = $this->normalizeRole($user->role);
        
        // Normalize required roles and check
        $normalizedRoles = array_map([$this, 'normalizeRole'], $roles);
        
        if (!in_array($userRole, $normalizedRoles, true)) {
            return response()->json(['error' => 'Unauthorized'], 403);
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

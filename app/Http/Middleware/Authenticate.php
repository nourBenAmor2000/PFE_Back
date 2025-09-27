<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Middleware\Authenticate as BaseAuthenticate;

class Authenticate 
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        return $request->expectsJson() ? null : route('login');
    }
    public function handle(Request $request, Closure $next)
    {
        // Vérifie l'authentification pour admin, agent OU client
        $user = Auth::guard('admin')->user() 
                ?? Auth::guard('agent')->user() 
                ?? Auth::guard('client')->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication required',
                'message' => 'Please log in as admin, agent or client',
                'available_routes' => [
                    'admin_login' => '/api/admin/login',
                    'agent_login' => '/api/agent/login', 
                    'client_login' => '/api/client/login'
                ]
            ], 401);
        }
    
        // Ajoute les informations de l'utilisateur à la requête
        $request->merge([
            'authenticated_user' => $user,
            'user_type' => $this->determineUserType($user)
        ]);
        
        return $next($request);
    }
    
    protected function determineUserType($user)
    {
        if ($user instanceof \Modules\Admin\App\Models\Admin) {
            return 'admin';
        }
        
        if ($user instanceof \Modules\Agent\App\Models\Agent) {
            return 'agent';
        }
        
        if ($user instanceof \Modules\Client\App\Models\Client) {
            return 'client';
        }
        
        return 'unknown';
    }
    
    protected function authenticate($request, array $guards)
    {
        if ($this->auth->guard('client')->check()) {
            return $this->auth->shouldUse('client');
        }

        $this->unauthenticated($request, ['client']);
    }
}

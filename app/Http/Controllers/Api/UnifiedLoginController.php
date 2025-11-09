<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UnifiedLoginController extends Controller
{
    /**
     * Adapte ces guards aux tiens (config/auth.php).
     * On teste dans cet ordre : admin -> agent -> client
     */
    protected array $guardsOrder = ['admin', 'agent', 'client'];

    /**
     * Mapping rôle -> dashboard.
     */
    protected array $dash = [
        'admin_global'    => '/app/admin',
        'admin_agence'    => '/app/agency',
        'agent_personnel' => '/app/agent',
        'client'          => '/app/client',
    ];

    /**
     * POST /api/unified/login
     * Body: { email, password }
     * Réponse: { type, role, dashboardPath, user, token? }
     */
    public function login(Request $req)
    {
        $data = $req->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        foreach ($this->guardsOrder as $guard) {
            // JWT: attempt() -> string token ; Session: attempt() -> bool
            $attempt = Auth::guard($guard)->attempt($data);

            if (! $attempt) {
                continue;
            }

            $user = Auth::guard($guard)->user();

            // Rôle final (priorité à la DB si colonne 'role' existe)
            $role = $this->resolveRole($guard, $user);

            // Dashboard
            $dashboard = $this->dash[$role] ?? '/app';

            // Token présent seulement si guard=JWT
            $token = is_string($attempt) ? $attempt : null;

            // Payload utilisateur minimal et normalisé
            $userMini = [
                'id'    => $user->id ?? null,
                'name'  => $user->name ?? ($user->username ?? null),
                'email' => $user->email ?? null,
                'role'  => $role,
            ];

            return response()->json([
                'type'          => $guard,     // 'admin' | 'agent' | 'client'
                'role'          => $role,      // 'admin_global' | 'admin_agence' | 'agent_personnel' | 'client'
                'dashboardPath' => $dashboard, // ex: '/app/admin'
                'user'          => $userMini,
                'token'         => $token,     // null si session
            ], 200);
        }

        return response()->json(['message' => 'Identifiants invalides'], 401);
    }

    /**
     * GET /api/me : retourne l'utilisateur courant + role + dashboard.
     * JWT: Authorization: Bearer <token> ; Session: cookie de session.
     */
    public function me(Request $req)
    {
        foreach ($this->guardsOrder as $guard) {
            Auth::shouldUse($guard);
            if ($u = Auth::guard($guard)->user()) {
                $role = $this->resolveRole($guard, $u);
                return response()->json([
                    'user'          => $u,
                    'role'          => $role,
                    'dashboardPath' => $this->dash[$role] ?? '/app',
                    'type'          => $guard,
                ], 200);
            }
        }
        return response()->json(['message' => 'Non authentifié'], 401);
    }

    /**
     * Détermine le rôle final. Si $user->role existe, on le normalise et on le renvoie.
     * Sinon on infère un défaut logique par guard.
     */
    protected function resolveRole(string $guard, $user): string
    {
        if (!empty($user->role)) {
            return $this->normalizeRole((string) $user->role);
        }

        // Défauts si la colonne role n'est pas renseignée
        return match ($guard) {
            'admin'  => 'admin_global',    // ✅ par défaut l'admin a accès global
            'agent'  => 'agent_personnel',
            'client' => 'client',
            default  => 'client',
        };
    }

    /**
     * Normalise les variantes possibles (majuscules/minuscules).
     */
    protected function normalizeRole(string $role): string
    {
        $r = strtolower(trim($role));
        return match ($r) {
            'admin_global', 'adminglobal', 'global_admin'   => 'admin_global',
            'admin_agence', 'adminagence', 'agency_admin'   => 'admin_agence',
            'agent_personnel', 'agentpersonnel', 'agent'    => 'agent_personnel',
            'client', 'customer', 'user'                    => 'client',
            default                                         => 'client',
        };
    }
}

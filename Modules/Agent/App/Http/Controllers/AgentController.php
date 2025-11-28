<?php

namespace Modules\Agent\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Modules\Agent\App\Models\Agent;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
class AgentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('agent::index');
    }
    public function showLoginForm()
    {
        return view('agent.login'); // Ensure this view exists
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('agent::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    // public function store(Request $request): RedirectResponse
    // {
    //     //
    // }

    /**
     * Show the specified resource.
     */
    // public function show($id)
    // {
    //     return view('agent::show');
    // }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('agent::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): RedirectResponse
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //
    }

// ========== AGENT (profil personnel) ==========

    // Voir le profil de l'agent connecté
    public function showProfile(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'agent' => Auth::guard('agent')->user()
        ]);
    }

    // Modifier le profil de l'agent connecté
    public function updateProfile(Request $request): JsonResponse
    {
        $agent = Auth::guard('agent')->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:agents,email,' . $agent->_id,
            'password' => 'sometimes|string|min:8|confirmed',
            'phone' => 'sometimes|string|max:20',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $agent->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour avec succès',
            'agent' => $agent
        ]);
    }

    // Supprimer le compte de l'agent connecté
    public function deleteProfile(): JsonResponse
    {
        $agent = Auth::guard('agent')->user();
        $agent->delete();

        Auth::guard('agent')->logout();

        return response()->json([
            'success' => true,
            'message' => 'Compte supprimé avec succès'
        ]);
    }



   public function register(Request $request): JsonResponse
{
    $validated = $request->validate([
        'name'      => 'required|string|max:255',
        'email'     => 'required|string|email|max:255|unique:agents',
        'password'  => 'required|string|min:8',
        'phone'     => 'nullable|string|max:20',
        'agency_id' => 'required|string|exists:agencys,_id',
        'role'      => 'required|in:' . implode(',', [Agent::ROLE_AGENT, Agent::ROLE_RH, Agent::ROLE_ADMIN])
    ]);

    try {
        $agent = new Agent();
        $agent->name      = $validated['name'];
        $agent->email     = $validated['email'];
        $agent->password  = Hash::make($validated['password']);
        $agent->phone     = $validated['phone'] ?? null;
        $agent->agency_id = $validated['agency_id'];
        $agent->role      = $validated['role'];

        // ✅ Générer et stocker le code + date d’expiration
        $code = random_int(100000, 999999);
        $agent->verification_code            = $code;
        $agent->verification_code_expires_at = now()->addMinutes(30);
        $agent->email_verified_at              = null;

        $agent->save();

        // ✅ Envoi email (exemple simple)
        \Mail::raw("Votre code de vérification est : {$code}", function ($message) use ($agent) {
            $message->to($agent->email)
                    ->subject('Vérification de votre email - HOMEZ');
        });

        return response()->json([
            'success'  => true,
            'message'  => "Agent enregistré. Un code de vérification a été envoyé à votre adresse email.",
            'agent'    => [
                'id'    => $agent->_id,
                'email' => $agent->email,
            ],
            'verify_required' => true,
        ], 201);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => "Erreur lors de l'enregistrement",
            'error'   => $e->getMessage()
        ], 500);
    }
}


public function verifyCode(Request $request): JsonResponse
{
    // 1) Validation des données reçues
    $validated = $request->validate([
        'email' => 'required|email|exists:agents,email',
        'code'  => 'required|string|size:6',
    ]);

    // 2) Récupérer l’agent
    $agent = Agent::where('email', $validated['email'])->first();

    if (!$agent) {
        return response()->json([
            'success' => false,
            'message' => 'Agent introuvable.',
        ], 404);
    }

    // 3) Vérifier que le code existe et n’a pas expiré
    if (
        empty($agent->verification_code) ||
        empty($agent->verification_code_expires_at) ||
        now()->greaterThan($agent->verification_code_expires_at)
    ) {
        return response()->json([
            'success' => false,
            'message' => 'Code invalide ou expiré.',
        ], 422);
    }

    // 4) Comparer le code (CAST en string pour éviter les problèmes int/string)
    if ((string)$agent->verification_code !== (string)$validated['code']) {
        return response()->json([
            'success' => false,
            'message' => 'Code invalide ou expiré.',
        ], 422);
    }

    // 5) Marquer l’email comme vérifié + nettoyer le code
    $agent->email_verified_at            = now();
    $agent->verification_code            = null;
    $agent->verification_code_expires_at = null;
    $agent->save();

    return response()->json([
        'success' => true,
        'message' => 'Email vérifié avec succès.',
    ]);
}






    // Méthode pour la connexion (déjà existante)
   public function login(Request $request): JsonResponse
{
    $request->validate([
        'email' => 'required|email',
        'password' => 'required|string'
    ]);

    \Log::info('Tentative de connexion', ['email' => $request->email]);

    $agent = Agent::where('email', $request->email)->first();

    if (!$agent) {
        \Log::warning('Email non trouvé', ['email' => $request->email]);
        return response()->json([
            'success' => false,
            'error' => 'Email not found'
        ], 401);
    }

    // 🔒 Bloquer si email non vérifié
    if (!$agent->hasVerifiedEmail()) {
        return response()->json([
            'success' => false,
            'error'   => 'Votre email n\'est pas encore vérifié. Veuillez vérifier votre boîte mail.'
        ], 403);
    }

    if (!Hash::check($request->password, $agent->password)) {
        \Log::warning('Mot de passe incorrect', ['agent_id' => $agent->_id]);
        return response()->json([
            'success' => false,
            'error' => 'Invalid password'
        ], 401);
    }

    try {
        if (!$token = Auth::guard('agent')->attempt($request->only(['email', 'password']))) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication failed'
            ], 401);
        }
    } catch (JWTException $e) {
        \Log::error('Erreur JWT', ['error' => $e->getMessage()]);
        return response()->json([
            'success' => false,
            'error' => 'Could not create token'
        ], 500);
    }

    return response()->json([
        'success' => true,
        'token' => $token,
        'agent' => $agent
    ]);
}

    
    // Méthode pour récupérer les informations de l'agent connecté (déjà existante)
    public function me(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'agent' => Auth::guard('agent')->user()
        ]);
    }

    // Méthode pour la déconnexion (déjà existante)
    public function logout(): JsonResponse
    {
        Auth::guard('agent')->logout();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out'
        ]);
    }

    // Méthode pour rafraîchir le token (déjà existante)
    public function refresh(): JsonResponse
    {
        $token = Auth::guard('agent')->refresh();

        return response()->json([
            'success' => true,
            'token' => $token
        ]);
    }
    

    /////////
   


    // ========== ADMIN AGENCE / RH : Gestion des agents ==========

    public function listAgents(): JsonResponse
    {
        $user = Auth::guard('agent')->user();
        $agents = Agent::where('agency_id', $user->agency_id)->get();
        return response()->json([
            'success' => true,
            'agents' => $agents
        ]);
    }

    public function show($id): JsonResponse
    {
        $admin = Auth::guard('agent')->user();
        $agent = Agent::where('_id', $id)
            ->where('agency_id', $admin->agency_id)
            ->first();

        if (!$agent) {
            return response()->json([
                'success' => false,
                'error' => 'Agent non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'agent' => $agent
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $admin = Auth::guard('agent')->user();
            
            if (!$admin) {
                return response()->json([
                    'success' => false,
                    'error' => 'Non authentifié',
                    'message' => 'Vous devez être connecté pour créer un agent'
                ], 401);
            }
            
            // Log for debugging
            \Log::info('Creating agent', [
                'admin_id' => $admin->_id,
                'admin_role' => $admin->role,
                'agency_id' => $admin->agency_id
            ]);

            $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:agents,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string',
            'role' => 'required|in:' . implode(',', [Agent::ROLE_AGENT, Agent::ROLE_RH, 'agent', 'rh']), // Allow both 'agent' and ROLE_AGENT
        ]);

        $agent = Agent::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
            'agency_id' => $admin->agency_id
        ]);

        \Log::info('Agent created successfully', [
            'agent_id' => $agent->_id,
            'admin_id' => $admin->_id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Agent créé avec succès',
            'agent' => $agent
        ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation error creating agent', [
                'errors' => $e->errors()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Erreur de validation',
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error creating agent', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la création de l\'agent',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updateAgent(Request $request, $id): JsonResponse
    {
        $admin = Auth::guard('agent')->user();

        $agent = Agent::where('_id', $id)
            ->where('agency_id', $admin->agency_id)
            ->first();

        if (!$agent) {
            return response()->json(['error' => 'Agent non trouvé'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:agents,email,' . $agent->_id,
            'phone' => 'sometimes|string|max:20',
            'role' => 'sometimes|in:' . implode(',', [Agent::ROLE_ADMIN, Agent::ROLE_RH, Agent::ROLE_AGENT]),
            'password' => 'nullable|string|min:8|confirmed'
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $agent->update($validated);

        return response()->json(['message' => 'Agent mis à jour', 'agent' => $agent]);
    }

    public function destroyAgent($id): JsonResponse
    {
        $admin = Auth::guard('agent')->user();

        $agent = Agent::where('_id', $id)
            ->where('agency_id', $admin->agency_id)
            ->first();

        if (!$agent) {
            return response()->json(['error' => 'Agent non trouvé'], 404);
        }

        $agent->delete();

        return response()->json(['message' => 'Agent supprimé']);
    }
public function sendResetLinkEmail(Request $request): JsonResponse
{
    // 1) Validation basique
    $request->validate([
        'email' => 'required|email',
    ]);

    try {
        // 2) On cherche l'agent
        $agent = Agent::where('email', $request->email)->first();

        // 🔒 Pour ne pas leak si l'email existe ou non:
        if (!$agent) {
            Log::warning('Password reset requested for unknown agent email: ' . $request->email);

            // On renvoie OK quand même (comme Laravel)
            return response()->json([
                'success' => true,
                'message' => 'If this email exists, a reset link has been sent.',
            ], 200);
        }

        // 3) Générer un token aléatoire
        $token = Str::random(60);

        // 4) Sauvegarder dans la table password_reset_tokens
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $agent->email],
            [
                'email'      => $agent->email,
                'token'      => Hash::make($token), // comme Laravel
                'created_at' => Carbon::now(),
            ]
        );

        // 5) Envoyer la notification (tu as déjà cette méthode dans le modèle Agent)
        $agent->sendPasswordResetNotification($token);

        Log::info('Password reset email sent to agent: ' . $agent->email);

        // 6) Réponse front
        return response()->json([
            'success' => true,
            'message' => 'If this email exists, a reset link has been sent.',
        ], 200);

    } catch (\Exception $e) {
        Log::error('Error sending agent reset link: ' . $e->getMessage());

        // On renvoie quand même 200 pour ne pas casser le front
        return response()->json([
            'success' => false,
            'message' => 'An error occurred while sending reset link.',
        ], 200);
    }
}
/**
     * Appliquer le nouveau mot de passe (Agent)
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::broker('agents')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Agent $agent, string $password) {
                $agent->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'message' => __($status),
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => __($status),
        ], 400);
    }
    
}


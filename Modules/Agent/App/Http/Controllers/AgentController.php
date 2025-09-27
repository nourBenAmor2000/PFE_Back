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



    // Méthode pour l'enregistrement d'un nouvel agent
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:agents',
            'password' => 'required|string|min:8', // Ajout de la confirmation
            'phone' => 'nullable|string|max:20',
            'agency_id' => 'required|string|exists:agencys,_id',
            'role' => 'required|in:' . implode(',', [Agent::ROLE_AGENT, Agent::ROLE_RH, Agent::ROLE_ADMIN])

        ]);
    
        try {
            $agent = Agent::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']), // Hash correct
                'phone' => $validated['phone'] ?? null,
                'agency_id' => $request->agency_id,
                'role' => $validated['role']

            ]);
    
            $agent->sendEmailVerificationNotification();
    
            return response()->json([
                'success' => true,
                'message' => 'Agent enregistré avec succès. Email de vérification envoyé.',
                'agent' => $agent
            ], 201);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur lors de l'enregistrement",
                'error' => $e->getMessage()
            ], 500);
        }
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

    \Log::info('Agent trouvé', ['agent_id' => $agent->_id]);

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
        return response()->json(['agents' => $agents]);
    }

    public function show($id): JsonResponse
    {
        $admin = Auth::guard('agent')->user();
        $agent = Agent::where('_id', $id)
            ->where('agency_id', $admin->agency_id)
            ->first();

        if (!$agent) {
            return response()->json(['error' => 'Agent non trouvé'], 404);
        }

        return response()->json(['agent' => $agent]);
    }

    public function store(Request $request): JsonResponse
    {
        $admin = Auth::guard('agent')->user();

        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:agents,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string',
            'role' => 'required|in:admin_agence,rh,personnel',
        ]);

        $agent = Agent::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'role' => $request->role,
            'agency_id' => $admin->agency_id
        ]);

        return response()->json(['message' => 'Agent créé', 'agent' => $agent]);
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
            'role' => 'sometimes|in:admin_agence,rh,personnel',
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


}

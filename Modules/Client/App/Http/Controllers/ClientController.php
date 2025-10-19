<?php

namespace Modules\Client\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Modules\Client\App\Models\Client;
use Tymon\JWTAuth\Exceptions\JWTException;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
   public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'clients' => Client::all()
        ]);
    }

    public function showLoginForm()
    {
        return view('client.login'); // Ensure this view exists
    }
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('client::create');
    }

    /**
     * Store a newly created resource in storage.
     */
   public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:clients',
            'email' => 'required|string|email|max:255|unique:clients',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
        ]);

        $client = Client::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Client créé avec succès',
            'client' => $client
        ], 201);
    }

    

    /**
     * Show the specified resource.
     */
    public function show($id): JsonResponse
    {
        $client = Client::find($id);

        if (!$client) {
            return response()->json(['success' => false, 'message' => 'Client non trouvé'], 404);
        }

        return response()->json(['success' => true, 'client' => $client]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('client::edit');
    }

    /**
     * Update the specified resource in storage.
     */
   public function update(Request $request, $id): JsonResponse
    {
        $client = Client::find($id);

        if (!$client) {
            return response()->json(['success' => false, 'message' => 'Client non trouvé'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|max:255|unique:clients,username,' . $client->id,
            'email' => 'sometimes|string|email|max:255|unique:clients,email,' . $client->id,
            'password' => 'nullable|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $client->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Client mis à jour avec succès',
            'client' => $client
        ]);
    }


    /**
     * Remove the specified resource from storage.
     */
   public function destroy($id): JsonResponse
    {
        $client = Client::find($id);

        if (!$client) {
            return response()->json(['success' => false, 'message' => 'Client non trouvé'], 404);
        }

        $client->delete();

        return response()->json([
            'success' => true,
            'message' => 'Client supprimé avec succès'
        ]);
    }


    public function showProfile(): JsonResponse
    {
        $client = Auth::guard('client')->user();
    
        if (!$client) {
            return response()->json(['success' => false, 'message' => 'Client non trouvé'], 404);
        }
    
        return response()->json(['success' => true, 'client' => $client]);
    }
    
    public function updateProfile(Request $request): JsonResponse
    {
        $client = Auth::guard('client')->user();
    
        if (!$client) {
            return response()->json(['success' => false, 'message' => 'Client non trouvé'], 404);
        }
    
        $validatedData = $request->validate([
            'name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|max:255|unique:clients,username,' . $client->_id,
            'email' => 'sometimes|string|email|max:255|unique:clients,email,' . $client->_id,
            'password' => 'sometimes|string|min:8',
            'phone' => 'nullable|string|max:20',
        ]);
    
        if (!empty($validatedData['password'])) {
            $validatedData['password'] = Hash::make($validatedData['password']);
        }
    
        $client->update($validatedData);
    
        return response()->json(['success' => true, 'message' => 'Profil mis à jour avec succès', 'client' => $client]);
    }
    
    public function deleteProfile(): JsonResponse
    {
        $client = Auth::guard('client')->user();
    
        if (!$client) {
            return response()->json(['success' => false, 'message' => 'Client non trouvé'], 404);
        }
    
        $client->delete();
    
        return response()->json(['success' => true, 'message' => 'Compte supprimé avec succès']);
    }
    



    /**
     * Enregistrement d'un nouveau client
     */
    public function register(Request $request): JsonResponse
{
    try {
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:clients',
            'email' => 'required|string|email|max:255|unique:clients',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20'
        ]);

        $client = Client::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'role'     => Client::ROLE_Client, // <-- force "Client"

        ]);

        // Solution 1: Envoi direct sans event
        $client->sendEmailVerificationNotification();

        // OU Solution 2: Avec event (doit être configuré correctement)
        // event(new \Illuminate\Auth\Events\Registered($client));

        $token = JWTAuth::fromUser($client);

        return response()->json([
            'success' => true,
            'message' => 'Client registered successfully. Verification email sent.',
            'client' => $client,
            'token' => $token,
        ], 201);

    } catch (\Exception $e) {
        \Log::error('Registration error: '.$e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Registration failed. Please try again.',
            'error' => env('APP_DEBUG') ? $e->getMessage() : null
        ], 500);
    }
}

    /**
     * Connexion du client
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        \Log::info('Tentative de connexion client', ['email' => $request->email]);

        $client = Client::where('email', $request->email)->first();

        if (!$client) {
            \Log::warning('Email client non trouvé', ['email' => $request->email]);
            return response()->json([
                'success' => false,
                'error' => 'Email not found'
            ], 401);
        }

        if (!Hash::check($request->password, $client->password)) {
            \Log::warning('Mot de passe client incorrect', ['client_id' => $client->_id]);
            return response()->json([
                'success' => false,
                'error' => 'Invalid password'
            ], 401);
        }

        try {
            if (!$token = Auth::guard('client')->attempt($request->only(['email', 'password']))) {
                return response()->json([
                    'success' => false,
                    'error' => 'Authentication failed'
                ], 401);
            }
        } catch (JWTException $e) {
            \Log::error('Erreur JWT client', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Could not create token'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'token' => $token,
            'client' => $client
        ]);
    }

    /**
     * Récupérer les informations du client connecté
     */
    public function me(): JsonResponse
    {
        $client = Auth::guard('client')->user();
        
        if (!$client) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthenticated'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'client' => $client
        ]);
    }

    /**
     * Déconnexion du client
     */
    public function logout(): JsonResponse
    {
        Auth::guard('client')->logout();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out'
        ]);
    }

    /**
     * Rafraîchir le token client
     */
    public function refresh(): JsonResponse
    {
        try {
            $token = Auth::guard('client')->refresh();
            
            return response()->json([
                'success' => true,
                'token' => $token
            ]);
            
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Token refresh failed'
            ], 500);
        }
    }
}

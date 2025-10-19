<?php

namespace Modules\Agency\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Agency\App\Models\Agency;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Modules\Agent\App\Models\Agent;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;


class AgencyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'agencies' => Agency::all()
        ]);
    }
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('agency::create');
    }

    /**
     * Store a newly created resource in storage.
     */
   public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // 'name' => 'required|string|max:255',
            // 'email' => 'required|email|unique:agencies',
            // 'phone' => 'nullable|string|max:20',
            // 'address' => 'nullable|string|max:255',
            // 'name'    => 'required|string|max:255',
            'email'   => 'required|email|unique:agencys,email',
            'phone'   => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
        ]);

        $agency = Agency::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Agence créée avec succès',
            'agency' => $agency
        ]);
    }

    /**
     * Show the specified resource.
     */
    public function show($id): JsonResponse
    {
        $agency = Agency::find($id);
        if (!$agency) {
            return response()->json(['error' => 'Agence non trouvée'], 404);
        }
        return response()->json(['success' => true, 'agency' => $agency]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('agency::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $agency = Agency::find($id);
        if (!$agency) {
            return response()->json(['error' => 'Agence non trouvée'], 404);
        }

        $validated = $request->validate([
            // 'name' => 'sometimes|string|max:255',
            // 'email' => 'sometimes|email|unique:agencies,email,' . $agency->id,
            // 'phone' => 'nullable|string|max:20',
            // 'address' => 'nullable|string|max:255',
            'name'    => 'sometimes|string|max:255',
            'email'   => 'sometimes|email|unique:agencys,email,' . $agency->_id . ',_id',
            'phone'   => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
        ]);

        $agency->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Agence mise à jour avec succès',
            'agency' => $agency
        ]);
    }
    /**
     * Remove the specified resource from storage.
     */
   public function destroy($id): JsonResponse
    {
        $agency = Agency::find($id);
        if (!$agency) {
            return response()->json(['error' => 'Agence non trouvée'], 404);
        }
        $agency->delete();

        return response()->json([
            'success' => true,
            'message' => 'Agence supprimée avec succès'
        ]);
    }
   
    /**
     * Inscription d’un Admin Agence
     * - crée (ou associe) une Agence
     * - crée un Agent role=admin_agence lié à l’agence
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            // Admin user
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:agents,email',
            'password' => 'required|string|min:8|confirmed',
            'phone'    => 'nullable|string|max:20',
            // Agency (si tu veux la créer à l’inscription)
            'agency.name'    => 'required|string|max:255',
            'agency.email'   => ['nullable','email', Rule::unique('agencys','email')], // si tu as le champ email
            'agency.phone'   => 'nullable|string|max:20',
            'agency.address' => 'nullable|string|max:255',
            'agency.city'    => 'nullable|string|max:255',
            'agency.username'=> 'nullable|string|max:255',
            'agency.logo'    => 'nullable|string',
            'agency.location'=> 'nullable|string',
        ]);

        // 1) Créer l’agence
        $agencyPayload = array_filter($data['agency'] ?? []);
        if (empty($agencyPayload['name'])) {
            return response()->json(['success'=>false,'message'=>'Agency name required'], 422);
        }
        $agency = Agency::create([
            'name'     => $agencyPayload['name'],
            'username' => $agencyPayload['username'] ?? null,
            'address'  => $agencyPayload['address']  ?? null,
            'phone'    => $agencyPayload['phone']    ?? null,
            'logo'     => $agencyPayload['logo']     ?? null,
            'location' => $agencyPayload['location'] ?? null,
            'city'     => $agencyPayload['city']     ?? null,
            // ajoute 'email' si présent dans $fillable et la collection
        ]);

        // 2) Créer l’admin lié à l’agence
        $admin = Agent::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            'phone'     => $data['phone'] ?? null,
            'role'      => Agent::ROLE_ADMIN, // 'admin_agence'
            'agency_id' => $agency->_id,
        ]);

        // (optionnel) email vérification
        $admin->sendEmailVerificationNotification();

        // 3) Token
        $token = JWTAuth::fromUser($admin);

        return response()->json([
            'success' => true,
            'message' => 'Admin agence enregistré',
            'token'   => $token,
            'user'    => $admin,
            'agency'  => $agency,
        ], 201);
    }

    /** Login Admin Agence */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'=>'required|email',
            'password'=>'required|string',
        ]);

        // tenter via guard 'agent'
        if (!$token = Auth::guard('agent')->attempt($request->only(['email','password']))) {
            return response()->json(['success'=>false,'message'=>'Invalid credentials'], 401);
        }

        $user = Auth::guard('agent')->user();
        if ($user->role !== Agent::ROLE_ADMIN) {
            return response()->json(['success'=>false,'message'=>'Not an agency admin'], 403);
        }

        return response()->json([
            'success'=>true,
            'token'=>$token,
            'user'=>$user
        ]);
    }

    public function me(): JsonResponse
    {
        return response()->json([
            'success'=>true,
            'user'=>Auth::guard('agent')->user()
        ]);
    }

    public function logout(): JsonResponse
    {
        Auth::guard('agent')->logout();
        return response()->json(['success'=>true,'message'=>'Logged out']);
    }

    public function refresh(): JsonResponse
    {
        $token = Auth::guard('agent')->refresh();
        return response()->json(['success'=>true,'token'=>$token]);
    }

    
    
    // === EXISTANTS: index, show, store, update, destroy ===
    // Ajoute ci-dessous les versions SCOPÉES pour admin_agence

    /** Renvoie uniquement sa propre agence */

     public function myAgencyIndex(): JsonResponse
    {
        $agencyId = auth('agent')->user()->agency_id;
        $agency = Agency::where('_id', $agencyId)->get();
        return response()->json(['success'=>true,'agencies'=>$agency]);
    }

    /** Voir une agence si (et seulement si) c’est la sienne */
    public function showScoped($id): JsonResponse
    {
        $agencyId = auth('agent')->user()->agency_id;
        $agency = Agency::where('_id',$id)->where('_id',$agencyId)->first();
        if (!$agency) return response()->json(['error'=>'Agence non trouvée'],404);
        return response()->json(['success'=>true,'agency'=>$agency]);
    }

    /** Créer une agence et la lier à l’admin courant (optionnel) */
    public function storeScoped(Request $request): JsonResponse
    {
        $user = auth('agent')->user();
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'username'=> 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone'   => 'nullable|string|max:20',
            'logo'    => 'nullable|string',
            'location'=> 'nullable|string',
            'city'    => 'nullable|string|max:255',
        ]);

        $agency = Agency::create($validated);

        // si tu souhaites re-lier l’admin à cette agence :
        $user->agency_id = $agency->_id;
        $user->save();

        return response()->json(['success'=>true,'message'=>'Agence créée','agency'=>$agency],201);
    }

    /** MAJ agence si c’est la sienne */
    public function updateScoped(Request $request, $id): JsonResponse
    {
        $agencyId = auth('agent')->user()->agency_id;
        $agency = Agency::where('_id',$id)->where('_id',$agencyId)->first();
        if (!$agency) return response()->json(['error'=>'Agence non trouvée'],404);

        $validated = $request->validate([
            'name'    => 'sometimes|string|max:255',
            'username'=> 'sometimes|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone'   => 'nullable|string|max:20',
            'logo'    => 'nullable|string',
            'location'=> 'nullable|string',
            'city'    => 'nullable|string|max:255',
        ]);

        $agency->update($validated);
        return response()->json(['success'=>true,'message'=>'Agence mise à jour','agency'=>$agency]);
    }

    /** Suppression agence si c’est la sienne (à VALIDER côté métier) */
    public function destroyScoped($id): JsonResponse
    {
        $agencyId = auth('agent')->user()->agency_id;
        $agency = Agency::where('_id',$id)->where('_id',$agencyId)->first();
        if (!$agency) return response()->json(['error'=>'Agence non trouvée'],404);

        $agency->delete();
        return response()->json(['success'=>true,'message'=>'Agence supprimée']);
    }

    

    

    
}

<?php

namespace Modules\Admin\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Modules\Admin\App\Models\Admin;
use Tymon\JWTAuth\Exceptions\JWTException;
use Modules\Agent\App\Models\Agent;
use Illuminate\Support\Facades\Hash;
use Modules\Attribute\App\Models\Attribute;
use Illuminate\Validation\Rule;
use Modules\Category\App\Models\Category;
use Modules\Client\App\Models\Client;
use Modules\Contract\App\Models\Contract;
use Illuminate\Support\Carbon;
use Modules\Logement\App\Models\Logement;
use Modules\PaymentContracts\App\Models\PaymentContracts;
use Modules\Review\App\Models\Review;
use Modules\SubCategory\App\Models\SubCategory;
use Modules\Visit\App\Models\Visit;

class AdminController extends Controller
{
    public function showLoginForm()
    {
        return view('admin.login'); // Ensure this view exists
    }
public function register(Request $request): JsonResponse
{
    // Validation des champs
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:admins',
        'password' => 'required|string|min:6|confirmed',
    ]);

    try {
        // Création de l'admin
        $admin = Admin::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
        ]);

        // Génération du token JWT
        $token = Auth::guard('admin')->login($admin);

        return response()->json([
            'success' => true,
            'message' => 'Admin registered successfully',
            'token' => $token,
            'admin' => $admin
        ], 201);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => 'Registration failed: ' . $e->getMessage()
        ], 500);
    }
}


    public function login(Request $request): JsonResponse
    {
        $credentials = $request->only(['email', 'password']);

        try {
            if (!$token = Auth::guard('admin')->attempt($credentials)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid credentials'
                ], 401);
            }
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to login, please try again'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Admin login successful',
            'token' => $token,
            'admin' => Auth::guard('admin')->user()
        ]);
    }

    public function me(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'admin' => Auth::guard('admin')->user()
        ]);
    }

    public function logout(): JsonResponse
    {
        Auth::guard('admin')->logout();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out'
        ]);
    }

    public function refresh(): JsonResponse
    {
        $token = Auth::guard('admin')->refresh();

        return response()->json([
            'success' => true,
            'token' => $token
        ]);
    }


    ////////////////////////////////////////////////
/* =========================
 * Section: Agency CRUD (ADMIN authentifié)
 * ========================= */

/**
 * Vérifie que l'utilisateur est authentifié via le guard `admin`.
 */
private function ensureAdminAuthenticated(): ?\Illuminate\Http\JsonResponse
{
    if (!\Illuminate\Support\Facades\Auth::guard('admin')->check()) {
        return response()->json([
            'success' => false,
            'error'   => 'Unauthenticated: admin token required'
        ], 401);
    }
    return null;
}

/**
 * GET /admin/agencies
 * Liste toutes les agences (admin authentifié)
 */
public function agenciesIndex(Request $request): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $q = \Modules\Agency\App\Models\Agency::query();

    // Filtres optionnels
    if ($request->filled('search')) {
        $q->where('name', 'like', '%'.$request->search.'%');
    }
    if ($request->filled('city')) {
        $q->where('city', $request->city);
    }

    // Pagination (par défaut 20)
    $perPage = (int) $request->get('per_page', 20);
    $data = $q->orderBy('_id', 'desc')->paginate($perPage);

    return response()->json([
        'success'  => true,
        'agencies' => $data,
    ]);
}

/**
 * POST /admin/agencies
 * Créer une agence (admin authentifié)
 */
public function agenciesStore(Request $request): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    // Champs conformes à ton modèle Agency ($fillable)
    $validated = $request->validate([
        'name'     => 'required|string|max:255',
        'username' => 'nullable|string|max:255',
        'address'  => 'nullable|string|max:255',
        'phone'    => 'nullable|string|max:20',
        'logo'     => 'nullable|string',
        'location' => 'nullable|string',
        'city'     => 'nullable|string|max:255',
    ]);

    $agency = \Modules\Agency\App\Models\Agency::create($validated);

    return response()->json([
        'success' => true,
        'message' => 'Agence créée avec succès',
        'agency'  => $agency,
    ], 201);
}

/**
 * GET /admin/agencies/{id}
 * Voir une agence (admin authentifié)
 */
public function agenciesShow(string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $agency = \Modules\Agency\App\Models\Agency::find($id);
    if (!$agency) {
        return response()->json(['success' => false, 'error' => 'Agence non trouvée'], 404);
    }

    return response()->json(['success' => true, 'agency' => $agency]);
}

/**
 * PUT /admin/agencies/{id}
 * Mettre à jour une agence (admin authentifié)
 */
public function agenciesUpdate(Request $request, string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $agency = \Modules\Agency\App\Models\Agency::find($id);
    if (!$agency) {
        return response()->json(['success' => false, 'error' => 'Agence non trouvée'], 404);
    }

    $validated = $request->validate([
        'name'     => 'sometimes|string|max:255',
        'username' => 'sometimes|string|max:255',
        'address'  => 'nullable|string|max:255',
        'phone'    => 'nullable|string|max:20',
        'logo'     => 'nullable|string',
        'location' => 'nullable|string',
        'city'     => 'nullable|string|max:255',
    ]);

    $agency->update($validated);

    return response()->json([
        'success' => true,
        'message' => 'Agence mise à jour avec succès',
        'agency'  => $agency,
    ]);
}

/**
 * DELETE /admin/agencies/{id}
 * Supprimer une agence (admin authentifié)
 */
public function agenciesDestroy(string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $agency = \Modules\Agency\App\Models\Agency::find($id);
    if (!$agency) {
        return response()->json(['success' => false, 'error' => 'Agence non trouvée'], 404);
    }

    $agency->delete();

    return response()->json([
        'success' => true,
        'message' => 'Agence supprimée avec succès',
    ]);
}

/* =========================
 * Section: Agent CRUD (ADMIN authentifié)
 * ========================= */

// GET /admin/agents  (liste + filtres)
public function agentsIndex(Request $request): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $q = Agent::query();

    // Filtres optionnels
    if ($request->filled('search')) {
        $s = $request->search;
        $q->where(function($qq) use ($s) {
            $qq->where('name','like','%'.$s.'%')
               ->orWhere('email','like','%'.$s.'%')
               ->orWhere('phone','like','%'.$s.'%');
        });
    }
    if ($request->filled('role')) {
        $q->where('role', $request->role); // admin_agence | rh | agent
    }
    if ($request->filled('agency_id')) {
        $q->where('agency_id', $request->agency_id);
    }

    $perPage = (int) $request->get('per_page', 20);
    $data = $q->orderBy('_id', 'desc')->paginate($perPage);

    return response()->json(['success'=>true, 'agents'=>$data]);
}

// POST /admin/agents  (créer)
public function agentsStore(Request $request): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $validated = $request->validate([
        'name'      => 'required|string|max:255',
        'email'     => 'required|email|max:255|unique:agents,email',
        'password'  => 'required|string|min:8|confirmed',
        'phone'     => 'nullable|string|max:20',
        'agency_id' => 'required|string|exists:agencys,_id', // ta collection s’appelle "agencys"
        'role'      => 'required|in:'.implode(',', [Agent::ROLE_ADMIN, Agent::ROLE_RH, Agent::ROLE_AGENT]),
    ]);

    $agent = Agent::create([
        'name'      => $validated['name'],
        'email'     => $validated['email'],
        'password'  => Hash::make($validated['password']),
        'phone'     => $validated['phone'] ?? null,
        'agency_id' => $validated['agency_id'],
        'role'      => $validated['role'],
    ]);

    // (optionnel) email de vérification si tu l’utilises réellement
    // $agent->sendEmailVerificationNotification();

    return response()->json([
        'success'=>true,
        'message'=>'Agent créé avec succès',
        'agent'=>$agent
    ], 201);
}

// GET /admin/agents/{id}  (détail)
public function agentsShow(string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $agent = Agent::find($id);
    if (!$agent) {
        return response()->json(['success'=>false,'error'=>'Agent non trouvé'], 404);
    }
    return response()->json(['success'=>true,'agent'=>$agent]);
}

// PUT /admin/agents/{id}  (modifier)
public function agentsUpdate(Request $request, string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $agent = Agent::find($id);
    if (!$agent) {
        return response()->json(['success'=>false,'error'=>'Agent non trouvé'], 404);
    }

    $validated = $request->validate([
        'name'      => 'sometimes|string|max:255',
        'email'     => 'sometimes|email|max:255|unique:agents,email,'.$agent->_id.',_id',
        'phone'     => 'sometimes|string|max:20',
        'agency_id' => 'sometimes|string|exists:agencys,_id',
        'role'      => 'sometimes|in:'.implode(',', [Agent::ROLE_ADMIN, Agent::ROLE_RH, Agent::ROLE_AGENT]),
        'password'  => 'nullable|string|min:8|confirmed',
    ]);

    if (!empty($validated['password'])) {
        $validated['password'] = Hash::make($validated['password']);
    }

    $agent->update($validated);

    return response()->json([
        'success'=>true,
        'message'=>'Agent mis à jour avec succès',
        'agent'=>$agent
    ]);
}

// DELETE /admin/agents/{id}  (supprimer)
public function agentsDestroy(string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $agent = Agent::find($id);
    if (!$agent) {
        return response()->json(['success'=>false,'error'=>'Agent non trouvé'], 404);
    }

    $agent->delete();

    return response()->json([
        'success'=>true,
        'message'=>'Agent supprimé avec succès'
    ]);
}

/* =========================
 * Section: Attribute CRUD (ADMIN authentifié)
 * ========================= */

// GET /admin/attributes
public function attributesIndex(Request $request): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $q = Attribute::query();

    if ($request->filled('search')) {
        $q->where('name', 'like', '%'.$request->search.'%');
    }
    if ($request->filled('type')) {
        $q->where('type', $request->type); // bool | list | text | number
    }

    $perPage = (int) $request->get('per_page', 20);
    $data = $q->orderBy('name')->paginate($perPage);

    return response()->json(['success'=>true, 'attributes'=>$data]);
}

// POST /admin/attributes
public function attributesStore(Request $request): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $validated = $request->validate([
        'name' => ['required','string','max:255','unique:attributes,name'],
        'type' => ['required', Rule::in(['bool','list','text','number'])],
    ]);

    $attr = Attribute::create($validated);

    return response()->json([
        'success'=>true,
        'message'=>'Attribut créé avec succès',
        'attribute'=>$attr
    ], 201);
}

// GET /admin/attributes/{id}
public function attributesShow(string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $attr = Attribute::find($id);
    if (!$attr) return response()->json(['success'=>false,'error'=>'Attribut non trouvé'], 404);

    return response()->json(['success'=>true,'attribute'=>$attr]);
}

// PUT /admin/attributes/{id}
public function attributesUpdate(Request $request, string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $attr = Attribute::find($id);
    if (!$attr) return response()->json(['success'=>false,'error'=>'Attribut non trouvé'], 404);

    $validated = $request->validate([
        'name' => [
            'sometimes','string','max:255',
            Rule::unique('attributes','name')->ignore($attr->_id ?? $attr->id, '_id'), // Mongo
        ],
        'type' => ['sometimes', Rule::in(['bool','list','text','number'])],
    ]);

    $attr->update($validated);

    return response()->json([
        'success'=>true,
        'message'=>'Attribut mis à jour avec succès',
        'attribute'=>$attr
    ]);
}

// DELETE /admin/attributes/{id}
public function attributesDestroy(string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $attr = Attribute::find($id);
    if (!$attr) return response()->json(['success'=>false,'error'=>'Attribut non trouvé'], 404);

    $attr->delete();

    return response()->json(['success'=>true,'message'=>'Attribut supprimé avec succès']);
}
/* =========================
 * Section: Category CRUD (ADMIN authentifié)
 * ========================= */

// GET /admin/categories  (liste + filtres)
public function categoriesIndex(Request $request): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $q = Category::query();
    if ($request->filled('search')) {
        $q->where('name', 'like', '%'.$request->search.'%');
    }

    $perPage = (int) $request->get('per_page', 20);
    $data = $q->orderBy('name')->paginate($perPage);

    return response()->json(['success'=>true, 'categories'=>$data]);
}

// POST /admin/categories  (créer)
public function categoriesStore(Request $request): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $validated = $request->validate([
        'name' => ['required','string','max:255','unique:categorys,name'],
    ]);

    $category = Category::create($validated);

    return response()->json([
        'success'=>true,
        'message'=>'Catégorie créée avec succès',
        'category'=>$category
    ], 201);
}

// GET /admin/categories/{id}  (détail)
public function categoriesShow(string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $category = Category::find($id);
    if (!$category) {
        return response()->json(['success'=>false,'error'=>'Catégorie non trouvée'], 404);
    }
    return response()->json(['success'=>true,'category'=>$category]);
}

// PUT /admin/categories/{id}  (mettre à jour)
public function categoriesUpdate(Request $request, string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $category = Category::find($id);
    if (!$category) {
        return response()->json(['success'=>false,'error'=>'Catégorie non trouvée'], 404);
    }

    $validated = $request->validate([
        'name' => [
            'sometimes','string','max:255',
            Rule::unique('categorys','name')->ignore($category->_id ?? $category->id, '_id'), // Mongo
        ],
    ]);

    $category->update($validated);

    return response()->json([
        'success'=>true,
        'message'=>'Catégorie mise à jour avec succès',
        'category'=>$category
    ]);
}

// DELETE /admin/categories/{id}  (supprimer)
public function categoriesDestroy(string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $category = Category::find($id);
    if (!$category) {
        return response()->json(['success'=>false,'error'=>'Catégorie non trouvée'], 404);
    }

    $category->delete();

    return response()->json(['success'=>true,'message'=>'Catégorie supprimée avec succès']);
}

/* =========================
 * Section: Client CRUD (ADMIN authentifié)
 * ========================= */

// GET /admin/clients  (liste + filtres)
public function clientsIndex(Request $request): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $q = Client::query();

    // Filtres optionnels
    if ($request->filled('search')) {
        $s = $request->search;
        $q->where(function($qq) use ($s) {
            $qq->where('name', 'like', '%'.$s.'%')
               ->orWhere('username', 'like', '%'.$s.'%')
               ->orWhere('email', 'like', '%'.$s.'%')
               ->orWhere('phone', 'like', '%'.$s.'%');
        });
    }

    $perPage = (int) $request->get('per_page', 20);
    $data = $q->orderBy('_id', 'desc')->paginate($perPage);

    return response()->json(['success'=>true, 'clients'=>$data]);
}

// POST /admin/clients  (créer)
public function clientsStore(Request $request): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $validated = $request->validate([
        'name'     => 'required|string|max:255',
        'username' => 'required|string|max:255|unique:clients,username',
        'email'    => 'required|string|email|max:255|unique:clients,email',
        'password' => 'required|string|min:8|confirmed',
        'phone'    => 'nullable|string|max:20',
    ]);

    $client = Client::create([
        'name'     => $validated['name'],
        'username' => $validated['username'],
        'email'    => $validated['email'],
        'password' => Hash::make($validated['password']),
        'phone'    => $validated['phone'] ?? null,
        // par cohérence avec ton modèle:
        'role'     => Client::ROLE_Client,
    ]);

    // (optionnel) envoyer l’email de vérification si tu l’utilises réellement
    // $client->sendEmailVerificationNotification();

    return response()->json([
        'success'=>true,
        'message'=>'Client créé avec succès',
        'client'=>$client
    ], 201);
}

// GET /admin/clients/{id}  (détail)
public function clientsShow(string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $client = Client::find($id);
    if (!$client) {
        return response()->json(['success'=>false,'message'=>'Client non trouvé'], 404);
    }

    return response()->json(['success'=>true,'client'=>$client]);
}

// PUT /admin/clients/{id}  (modifier)
public function clientsUpdate(Request $request, string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $client = Client::find($id);
    if (!$client) {
        return response()->json(['success'=>false,'message'=>'Client non trouvé'], 404);
    }

    $validated = $request->validate([
        'name'     => 'sometimes|string|max:255',
        // Mongo: ignore par _id ; si jamais SQL, $client->id
        'username' => ['sometimes','string','max:255', Rule::unique('clients','username')->ignore($client->_id ?? $client->id, '_id')],
        'email'    => ['sometimes','string','email','max:255', Rule::unique('clients','email')->ignore($client->_id ?? $client->id, '_id')],
        'password' => 'nullable|string|min:8|confirmed',
        'phone'    => 'nullable|string|max:20',
    ]);

    if (!empty($validated['password'])) {
        $validated['password'] = Hash::make($validated['password']);
    }

    $client->update($validated);

    return response()->json([
        'success'=>true,
        'message'=>'Client mis à jour avec succès',
        'client'=>$client
    ]);
}

// DELETE /admin/clients/{id}  (supprimer)
public function clientsDestroy(string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $client = Client::find($id);
    if (!$client) {
        return response()->json(['success'=>false,'message'=>'Client non trouvé'], 404);
    }

    $client->delete();

    return response()->json([
        'success'=>true,
        'message'=>'Client supprimé avec succès'
    ]);
}


/* =========================
 * Section: Contract CRUD (ADMIN authentifié)
 * ========================= */

/**
 * GET /admin/contracts
 * Liste paginée + filtres
 */
public function contractsIndex(Request $request): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $q = Contract::query();

    // Filtres
    if ($request->filled('client_id')) {
        $q->where('client_id', $request->client_id);
    }
    if ($request->filled('logement_id')) {
        $q->where('logement_id', $request->logement_id);
    }
    if ($request->filled('date_from')) {
        $q->where('start_date', '>=', Carbon::parse($request->date_from));
    }
    if ($request->filled('date_to')) {
        $q->where('end_date', '<=', Carbon::parse($request->date_to));
    }
    if ($request->filled('amount_min')) {
        $q->where('amount', '>=', (float) $request->amount_min);
    }
    if ($request->filled('amount_max')) {
        $q->where('amount', '<=', (float) $request->amount_max);
    }
    if ($request->filled('has_payment')) {
        // true / false
        $has = filter_var($request->has_payment, FILTER_VALIDATE_BOOLEAN);
        $q->whereExists('payment', $has); // option simple; sinon joins logiques si besoin
    }

    $perPage = (int) $request->get('per_page', 20);
    $data = $q->orderBy('start_date', 'desc')->paginate($perPage);

    return response()->json(['success'=>true, 'contracts'=>$data]);
}

/**
 * POST /admin/contracts
 * Créer un contrat
 */
public function contractsStore(Request $request): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $validated = $request->validate([
        'client_id'   => ['required','string','exists:clients,_id'],
        'logement_id' => ['required','string','exists:logements,_id'],
        'start_date'  => ['required','date'],
        'end_date'    => ['required','date','after:start_date'],
        'amount'      => ['required','numeric','min:0'],
    ]);

    // (Optionnel) normaliser les dates
    $validated['start_date'] = Carbon::parse($validated['start_date']);
    $validated['end_date']   = Carbon::parse($validated['end_date']);

    $contract = Contract::create($validated);

    return response()->json([
        'success'=>true,
        'message'=>'Contrat créé avec succès',
        'contract'=>$contract
    ], 201);
}

/**
 * GET /admin/contracts/{id}
 * Détail d’un contrat
 */
public function contractsShow(string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $contract = Contract::find($id);
    if (!$contract) {
        return response()->json(['success'=>false,'error'=>'Contrat non trouvé'], 404);
    }

    return response()->json(['success'=>true,'contract'=>$contract]);
}

/**
 * PUT /admin/contracts/{id}
 * Mettre à jour un contrat
 */
public function contractsUpdate(Request $request, string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $contract = Contract::find($id);
    if (!$contract) {
        return response()->json(['success'=>false,'error'=>'Contrat non trouvé'], 404);
    }

    $validated = $request->validate([
        'client_id'   => ['sometimes','string','exists:clients,_id'],
        'logement_id' => ['sometimes','string','exists:logements,_id'],
        'start_date'  => ['sometimes','date'],
        'end_date'    => ['sometimes','date'],
        'amount'      => ['sometimes','numeric','min:0'],
    ]);

    // Cohérence des dates si une seule est fournie
    $newStart = isset($validated['start_date'])
        ? Carbon::parse($validated['start_date'])
        : ($contract->start_date ? Carbon::parse($contract->start_date) : null);

    $newEnd = isset($validated['end_date'])
        ? Carbon::parse($validated['end_date'])
        : ($contract->end_date ? Carbon::parse($contract->end_date) : null);

    if ($newStart && $newEnd && $newEnd->lt($newStart)) {
        return response()->json([
            'success'=>false,
            'error'=>'La date de fin doit être postérieure à la date de début.'
        ], 422);
    }

    if (isset($validated['start_date'])) $validated['start_date'] = $newStart;
    if (isset($validated['end_date']))   $validated['end_date']   = $newEnd;

    $contract->update($validated);

    return response()->json([
        'success'=>true,
        'message'=>'Contrat mis à jour avec succès',
        'contract'=>$contract
    ]);
}

/**
 * DELETE /admin/contracts/{id}
 * Supprimer un contrat
 */
public function contractsDestroy(string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $contract = Contract::find($id);
    if (!$contract) {
        return response()->json(['success'=>false,'error'=>'Contrat non trouvé'], 404);
    }

    // Si tu veux bloquer la suppression si paiement existe, décommente & adapte :
    // if ($contract->payment()->exists()) {
    //     return response()->json([
    //         'success'=>false,
    //         'error'=>'Impossible de supprimer: un paiement lié existe.'
    //     ], 409);
    // }

    $contract->delete();

    return response()->json([
        'success'=>true,
        'message'=>'Contrat supprimé avec succès'
    ]);
}
/* =========================
 * Section: Logement CRUD (ADMIN authentifié)
 * ========================= */

// GET /admin/logements  (liste + filtres)
public function logementsIndex(Request $request): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $q = Logement::query();

    // Filtres optionnels
    if ($request->filled('search')) {
        $s = $request->search;
        $q->where(function($qq) use ($s) {
            $qq->where('title','like','%'.$s.'%')
               ->orWhere('description','like','%'.$s.'%')
               ->orWhere('location','like','%'.$s.'%');
        });
    }
    if ($request->filled('agency_id'))   $q->where('agency_id',   $request->agency_id);
    if ($request->filled('category_id')) $q->where('category_id', $request->category_id);

    if ($request->filled('price_min')) $q->where('price', '>=', (float)$request->price_min);
    if ($request->filled('price_max')) $q->where('price', '<=', (float)$request->price_max);

    if ($request->filled('free')) {
        // accepte "true"/"false"/1/0
        $q->where('free', filter_var($request->free, FILTER_VALIDATE_BOOLEAN));
    }

    $perPage = (int) $request->get('per_page', 20);
    $data = $q->orderBy('title')->paginate($perPage);

    return response()->json(['success'=>true, 'logements'=>$data]);
}

// POST /admin/logements  (créer)
public function logementsStore(Request $request): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $validated = $request->validate([
        'title'       => ['required','string','max:255'],
        'description' => ['nullable','string'],
        'price'       => ['required','numeric','min:0'],
        'category_id' => ['required','string','exists:categorys,_id'], // collection "categorys"
        'agency_id'   => ['required','string','exists:agencys,_id'],    // ⚠ corrige "agencies" -> "agencys"
        'latitude'    => ['nullable','numeric','between:-90,90'],
        'longitude'   => ['nullable','numeric','between:-180,180'],
        'location'    => ['nullable','string','max:255'],
        'surface'     => ['nullable','integer','min:0'],
        'floor'       => ['nullable','integer'],
        'free'        => ['sometimes','boolean'],
    ]);

    if (!array_key_exists('free', $validated)) {
        $validated['free'] = true;
    }

    $logement = Logement::create($validated);

    return response()->json([
        'success'=>true,
        'message'=>'Logement créé avec succès',
        'logement'=>$logement
    ], 201);
}

// GET /admin/logements/{id}  (détail)
public function logementsShow(string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $logement = Logement::find($id);
    if (!$logement) {
        return response()->json(['success'=>false,'error'=>'Logement non trouvé'], 404);
    }
    return response()->json(['success'=>true,'logement'=>$logement]);
}

// PUT /admin/logements/{id}  (modifier)
public function logementsUpdate(Request $request, string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $logement = Logement::find($id);
    if (!$logement) {
        return response()->json(['success'=>false,'error'=>'Logement non trouvé'], 404);
    }

    $validated = $request->validate([
        'title'       => ['sometimes','string','max:255'],
        'description' => ['sometimes','nullable','string'],
        'price'       => ['sometimes','numeric','min:0'],
        'category_id' => ['sometimes','string','exists:categorys,_id'],
        'agency_id'   => ['sometimes','string','exists:agencys,_id'], // ⚠ même correction
        'latitude'    => ['sometimes','nullable','numeric','between:-90,90'],
        'longitude'   => ['sometimes','nullable','numeric','between:-180,180'],
        'location'    => ['sometimes','nullable','string','max:255'],
        'surface'     => ['sometimes','nullable','integer','min:0'],
        'floor'       => ['sometimes','nullable','integer'],
        'free'        => ['sometimes','boolean'],
    ]);

    $logement->update($validated);

    return response()->json([
        'success'=>true,
        'message'=>'Logement mis à jour avec succès',
        'logement'=>$logement
    ]);
}

// DELETE /admin/logements/{id}  (supprimer)
public function logementsDestroy(string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $logement = Logement::find($id);
    if (!$logement) {
        return response()->json(['success'=>false,'error'=>'Logement non trouvé'], 404);
    }

    $logement->delete();

    return response()->json([
        'success'=>true,
        'message'=>'Logement supprimé avec succès'
    ]);
}

/* =========================
 * Section: PaymentContracts CRUD (ADMIN authentifié)
 * ========================= */

// GET /admin/payment-contracts  (liste + filtres)
public function paymentContractsIndex(Request $request): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $q = PaymentContracts::query();

    // Filtres
    if ($request->filled('contract_id')) {
        $q->where('contract_id', $request->contract_id);
    }
    if ($request->filled('statut')) {
        // PENDING | PAID | FAILED | REFUNDED
        $q->where('statut', $request->statut);
    }
    if ($request->filled('date_from')) {
        $q->where('date_paiement', '>=', Carbon::parse($request->date_from));
    }
    if ($request->filled('date_to')) {
        $q->where('date_paiement', '<=', Carbon::parse($request->date_to));
    }
    if ($request->filled('amount_min')) {
        $q->where('montant', '>=', (float) $request->amount_min);
    }
    if ($request->filled('amount_max')) {
        $q->where('montant', '<=', (float) $request->amount_max);
    }
    if ($request->filled('reference')) {
        $q->where('reference_transaction', 'like', '%'.$request->reference.'%');
    }

    $perPage = (int) $request->get('per_page', 20);
    $data = $q->orderBy('date_paiement', 'desc')->paginate($perPage);

    return response()->json(['success'=>true, 'payments'=>$data]);
}

// POST /admin/payment-contracts  (créer)
public function paymentContractsStore(Request $request): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $validated = $request->validate([
        'contract_id'           => ['required','string','exists:contracts,_id'],
        'montant'               => ['required','numeric','min:0'],
        'methode_paiement'      => ['required','string','max:50'],
        'statut'                => ['required', Rule::in(['PENDING','PAID','FAILED','REFUNDED'])],
        'date_paiement'         => ['required','date'],
        'reference_transaction' => ['nullable','string','max:120'],
    ]);

    $validated['date_paiement'] = Carbon::parse($validated['date_paiement']);

    $payment = PaymentContracts::create($validated);

    return response()->json([
        'success'=>true,
        'message'=>'Paiement de contrat créé avec succès',
        'payment'=>$payment
    ], 201);
}

// GET /admin/payment-contracts/{id}  (détail)
public function paymentContractsShow(string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $payment = PaymentContracts::find($id);
    if (!$payment) {
        return response()->json(['success'=>false,'error'=>'Paiement non trouvé'], 404);
    }

    return response()->json(['success'=>true,'payment'=>$payment]);
}

// PUT /admin/payment-contracts/{id}  (modifier)
public function paymentContractsUpdate(Request $request, string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $payment = PaymentContracts::find($id);
    if (!$payment) {
        return response()->json(['success'=>false,'error'=>'Paiement non trouvé'], 404);
    }

    $validated = $request->validate([
        'contract_id'           => ['sometimes','string','exists:contracts,_id'],
        'montant'               => ['sometimes','numeric','min:0'],
        'methode_paiement'      => ['sometimes','string','max:50'],
        'statut'                => ['sometimes', Rule::in(['PENDING','PAID','FAILED','REFUNDED'])],
        'date_paiement'         => ['sometimes','date'],
        'reference_transaction' => ['sometimes','nullable','string','max:120'],
    ]);

    if (isset($validated['date_paiement'])) {
        $validated['date_paiement'] = Carbon::parse($validated['date_paiement']);
    }

    $payment->update($validated);

    return response()->json([
        'success'=>true,
        'message'=>'Paiement mis à jour avec succès',
        'payment'=>$payment
    ]);
}

// DELETE /admin/payment-contracts/{id}  (supprimer)
public function paymentContractsDestroy(string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $payment = PaymentContracts::find($id);
    if (!$payment) {
        return response()->json(['success'=>false,'error'=>'Paiement non trouvé'], 404);
    }

    $payment->delete();

    return response()->json([
        'success'=>true,
        'message'=>'Paiement supprimé avec succès'
    ]);
}

/* =========================
 * Section: Review CRUD (ADMIN authentifié)
 * ========================= */

// GET /admin/reviews  (liste + filtres)
public function reviewsIndex(Request $request): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $q = Review::query();

    // Filtres
    if ($request->filled('client_id'))   $q->where('client_id',   $request->client_id);
    if ($request->filled('logement_id')) $q->where('logement_id', $request->logement_id);
    if ($request->filled('rating_min'))  $q->where('rating', '>=', (int)$request->rating_min);
    if ($request->filled('rating_max'))  $q->where('rating', '<=', (int)$request->rating_max);
    if ($request->filled('has_comment')) {
        $has = filter_var($request->has_comment, FILTER_VALIDATE_BOOLEAN);
        $q->where(function ($qq) use ($has) {
            if ($has) $qq->whereNotNull('comment')->where('comment', '!=', '');
            else      $qq->whereNull('comment')->orWhere('comment', '');
        });
    }
    if ($request->filled('search')) {
        $q->where('comment', 'like', '%'.$request->search.'%');
    }

    $perPage = (int) $request->get('per_page', 20);
    $data = $q->orderBy('created_at', 'desc')->paginate($perPage);

    return response()->json(['success'=>true, 'reviews'=>$data]);
}

// POST /admin/reviews  (créer)
public function reviewsStore(Request $request): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $validated = $request->validate([
        'client_id'   => ['required','string','exists:clients,_id'],
        'logement_id' => ['required','string','exists:logements,_id'],
        'comment'     => ['nullable','string','max:2000'],
        'rating'      => ['required','integer','between:1,5'],
    ]);

    // Unicité client/logement (une seule review par logement et par client)
    $exists = Review::where('client_id', $validated['client_id'])
                    ->where('logement_id', $validated['logement_id'])
                    ->exists();
    if ($exists) {
        return response()->json([
            'success'=>false,
            'error'  => 'Ce client a déjà laissé une review pour ce logement.'
        ], 409);
    }

    $review = Review::create($validated);

    return response()->json([
        'success'=>true,
        'message'=>'Review créée avec succès',
        'review'=>$review
    ], 201);
}

// GET /admin/reviews/{id}  (détail)
public function reviewsShow(string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $review = Review::find($id);
    if (!$review) {
        return response()->json(['success'=>false,'error'=>'Review non trouvée'], 404);
    }
    return response()->json(['success'=>true,'review'=>$review]);
}

// PUT /admin/reviews/{id}  (modifier)
public function reviewsUpdate(Request $request, string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $review = Review::find($id);
    if (!$review) {
        return response()->json(['success'=>false,'error'=>'Review non trouvée'], 404);
    }

    $validated = $request->validate([
        'client_id'   => ['sometimes','string','exists:clients,_id'],
        'logement_id' => ['sometimes','string','exists:logements,_id'],
        'comment'     => ['sometimes','nullable','string','max:2000'],
        'rating'      => ['sometimes','integer','between:1,5'],
    ]);

    // Vérifier l’unicité si client/logement changent
    $newClient   = $validated['client_id']   ?? $review->client_id;
    $newLogement = $validated['logement_id'] ?? $review->logement_id;

    $dup = Review::where('client_id', $newClient)
                 ->where('logement_id', $newLogement)
                 ->where('_id', '!=', $review->_id)   // Mongo (_id)
                 ->exists();
    if ($dup) {
        return response()->json([
            'success'=>false,
            'error'  => 'Ce client a déjà une review pour ce logement.'
        ], 409);
    }

    $review->update($validated);

    return response()->json([
        'success'=>true,
        'message'=>'Review mise à jour avec succès',
        'review'=>$review
    ]);
}

// DELETE /admin/reviews/{id}  (supprimer)
public function reviewsDestroy(string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $review = Review::find($id);
    if (!$review) {
        return response()->json(['success'=>false,'error'=>'Review non trouvée'], 404);
    }

    $review->delete();

    return response()->json([
        'success'=>true,
        'message'=>'Review supprimée avec succès'
    ]);
}


/* =========================
 * Section: SubCategory CRUD (ADMIN authentifié)
 * ========================= */

// GET /admin/subcategories  (liste + filtres)
public function subcategoriesIndex(Request $request): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $q = SubCategory::query();

    // Filtres
    if ($request->filled('category_id')) $q->where('category_id', $request->category_id);
    if ($request->filled('search'))      $q->where('name', 'like', '%'.$request->search.'%');

    $perPage = (int) $request->get('per_page', 20);
    $data = $q->orderBy('name')->paginate($perPage);

    return response()->json(['success'=>true, 'subcategories'=>$data]);
}

// POST /admin/subcategories  (créer)
public function subcategoriesStore(Request $request): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $validated = $request->validate([
        'name' => [
            'required','string','max:255',
            Rule::unique('subcategorys','name')
                ->where(fn($q) => $q->where('category_id', $request->category_id)),
        ],
        'category_id' => ['required','string','exists:categorys,_id'],
    ]);

    $sub = SubCategory::create($validated);

    return response()->json([
        'success'=>true,
        'message'=>'Sous-catégorie créée avec succès',
        'subcategory'=>$sub
    ], 201);
}

// GET /admin/subcategories/{id}  (détail)
public function subcategoriesShow(string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $sub = SubCategory::find($id);
    if (!$sub) {
        return response()->json(['success'=>false,'error'=>'Sous-catégorie non trouvée'], 404);
    }
    return response()->json(['success'=>true,'subcategory'=>$sub]);
}

// PUT /admin/subcategories/{id}  (modifier)
public function subcategoriesUpdate(Request $request, string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $sub = SubCategory::find($id);
    if (!$sub) {
        return response()->json(['success'=>false,'error'=>'Sous-catégorie non trouvée'], 404);
    }

    // Si category_id n’est pas fourni, on conserve l’actuel pour l’unicité (nom,category_id)
    $categoryId = $request->input('category_id', $sub->category_id);

    $validated = $request->validate([
        'name' => [
            'sometimes','string','max:255',
            Rule::unique('subcategorys','name')
                ->ignore($sub->_id ?? $sub->id, '_id')
                ->where(fn($q) => $q->where('category_id', $categoryId)),
        ],
        'category_id' => ['sometimes','string','exists:categorys,_id'],
    ]);

    $sub->update($validated);

    return response()->json([
        'success'=>true,
        'message'=>'Sous-catégorie mise à jour avec succès',
        'subcategory'=>$sub
    ]);
}

// DELETE /admin/subcategories/{id}  (supprimer)
public function subcategoriesDestroy(string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $sub = SubCategory::find($id);
    if (!$sub) {
        return response()->json(['success'=>false,'error'=>'Sous-catégorie non trouvée'], 404);
    }

    $sub->delete();

    return response()->json([
        'success'=>true,
        'message'=>'Sous-catégorie supprimée avec succès'
    ]);
}
/* =========================
 * Section: Visit CRUD (ADMIN authentifié)
 * ========================= */

// GET /admin/visits  (liste + filtres)
public function visitsIndex(Request $request): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $q = Visit::query();

    // Filtres simples
    if ($request->filled('client_id'))   $q->where('client_id',   $request->client_id);
    if ($request->filled('logement_id')) $q->where('logement_id', $request->logement_id);

    // Filtre par agence via le logement
    if ($request->filled('agency_id')) {
        $agencyId = $request->agency_id;
        $q->whereHas('logement', function($qq) use ($agencyId) {
            $qq->where('agency_id', $agencyId);
        });
    }

    // Plage de dates (visit_date stockée en string/datetime)
    if ($request->filled('date_from')) $q->where('visit_date', '>=', Carbon::parse($request->date_from));
    if ($request->filled('date_to'))   $q->where('visit_date', '<=', Carbon::parse($request->date_to));

    $perPage = (int) $request->get('per_page', 20);
    $data = $q->orderBy('visit_date','desc')->paginate($perPage);

    return response()->json(['success'=>true, 'visits'=>$data]);
}

// POST /admin/visits  (créer)
public function visitsStore(Request $request): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $v = $request->validate([
        'client_id'   => ['required','string','exists:clients,_id'],
        'logement_id' => ['required','string','exists:logements,_id'],
        'visit_date'  => ['required','date'], // ex: "2025-11-01 14:00:00"
    ]);

    // Empêcher un doublon exact client/logement/date
    $exists = Visit::where('client_id', $v['client_id'])
        ->where('logement_id', $v['logement_id'])
        ->where('visit_date',  $v['visit_date'])
        ->exists();

    if ($exists) {
        return response()->json([
            'success'=>false,
            'error'  => 'Une visite existe déjà pour ce client, ce logement et cette date.'
        ], 409);
    }

    $visit = Visit::create($v);

    return response()->json([
        'success'=>true,
        'message'=>'Visite créée avec succès',
        'visit'=>$visit
    ], 201);
}

// GET /admin/visits/{id}  (détail)
public function visitsShow(string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $visit = Visit::find($id);
    if (!$visit) {
        return response()->json(['success'=>false,'error'=>'Visite non trouvée'], 404);
    }
    return response()->json(['success'=>true,'visit'=>$visit]);
}

// PUT /admin/visits/{id}  (modifier)
public function visitsUpdate(Request $request, string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $visit = Visit::find($id);
    if (!$visit) {
        return response()->json(['success'=>false,'error'=>'Visite non trouvée'], 404);
    }

    $v = $request->validate([
        'client_id'   => ['sometimes','string','exists:clients,_id'],
        'logement_id' => ['sometimes','string','exists:logements,_id'],
        'visit_date'  => ['sometimes','date'],
    ]);

    // Vérifier le doublon si un des champs change
    $newClient   = $v['client_id']   ?? $visit->client_id;
    $newLogement = $v['logement_id'] ?? $visit->logement_id;
    $newDate     = $v['visit_date']  ?? $visit->visit_date;

    $dup = Visit::where('client_id',$newClient)
        ->where('logement_id',$newLogement)
        ->where('visit_date',$newDate)
        ->where('_id','!=',$visit->_id) // Mongo: clé _id
        ->exists();

    if ($dup) {
        return response()->json([
            'success'=>false,
            'error'  => 'Une visite identique existe déjà.'
        ], 409);
    }

    $visit->update($v);

    return response()->json([
        'success'=>true,
        'message'=>'Visite mise à jour avec succès',
        'visit'=>$visit
    ]);
}

// DELETE /admin/visits/{id}  (supprimer)
public function visitsDestroy(string $id): \Illuminate\Http\JsonResponse
{
    if ($resp = $this->ensureAdminAuthenticated()) return $resp;

    $visit = Visit::find($id);
    if (!$visit) {
        return response()->json(['success'=>false,'error'=>'Visite non trouvée'], 404);
    }

    $visit->delete();

    return response()->json([
        'success'=>true,
        'message'=>'Visite supprimée avec succès'
    ]);
}


}
<?php

namespace Modules\Logement\App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\GeocodingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Modules\Logement\App\Models\Logement;
use Modules\Agency\App\Models\Agency;

use Modules\Category\App\Models\Category;


class LogementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Logement::query();

        // Auto-filter by agency_id for agents
        $user = Auth::guard('admin')->user() 
            ?? Auth::guard('agent')->user() 
            ?? Auth::guard('client')->user();
        
        if ($user instanceof \Modules\Agent\App\Models\Agent && $user->agency_id) {
            // Agents automatically see only their agency's properties
            $query->where('agency_id', $user->agency_id);
        }

        // Search filters
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        // Price filters
        if ($request->filled('price_min')) {
            $query->where('price', '>=', (float)$request->input('price_min'));
        }
        if ($request->filled('price_max')) {
            $query->where('price', '<=', (float)$request->input('price_max'));
        }

        // Status filter
        if ($request->filled('free')) {
            $query->where('free', filter_var($request->input('free'), FILTER_VALIDATE_BOOLEAN));
        }

        // Category filter
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        // Agency filter
        if ($request->filled('agency_id')) {
            $query->where('agency_id', $request->input('agency_id'));
        }

        // Map bounds filter
        if ($request->filled('bounds')) {
            $bounds = $request->input('bounds');
            if (isset($bounds['north']) && isset($bounds['south']) && 
                isset($bounds['east']) && isset($bounds['west'])) {
                $query->whereBetween('latitude', [(float)$bounds['south'], (float)$bounds['north']])
                      ->whereBetween('longitude', [(float)$bounds['west'], (float)$bounds['east']]);
            }
        }

        // Location-based search (requires latitude/longitude)
        if ($request->filled('location')) {
            $location = $request->input('location');
            // If location is provided as coordinates
            if (isset($location['lat']) && isset($location['lng'])) {
                $lat = (float)$location['lat'];
                $lng = (float)$location['lng'];
                $radius = (float)($location['radius'] ?? 10); // default 10km radius
                
                // Simple bounding box approximation (for MongoDB)
                $query->whereBetween('latitude', [$lat - ($radius / 111), $lat + ($radius / 111)])
                      ->whereBetween('longitude', [$lng - ($radius / (111 * cos(deg2rad($lat)))), $lng + ($radius / (111 * cos(deg2rad($lat))))]);
            }
        }

        // Sort
        $sortBy = $request->input('sort', 'title');
        $sortDir = $request->input('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = (int) $request->get('per_page', 50);
        $logements = $query->paginate($perPage);

        return response()->json([
            'success'   => true,
            'logements' => $logements->items(),
            'total' => $logements->total(),
            'per_page' => $logements->perPage(),
            'current_page' => $logements->currentPage(),
        ]);
    }

    /**
     * Map search endpoint - optimized for map view
     */
    public function mapSearch(Request $request): JsonResponse
    {
        $query = Logement::query();

        // Map bounds (required for map search)
        if ($request->filled('bounds')) {
            $bounds = $request->input('bounds');
            if (isset($bounds['north']) && isset($bounds['south']) && 
                isset($bounds['east']) && isset($bounds['west'])) {
                $query->whereBetween('latitude', [(float)$bounds['south'], (float)$bounds['north']])
                      ->whereBetween('longitude', [(float)$bounds['west'], (float)$bounds['east']]);
            }
        }

        // Location text search with geocoding
        if ($request->filled('location')) {
            $location = $request->input('location');
            
            // Try geocoding first
            $geocodingService = app(GeocodingService::class);
            $geocoded = $geocodingService->geocode($location);
            
            if ($geocoded) {
                // Use geocoded coordinates for radius search
                $lat = $geocoded['lat'];
                $lng = $geocoded['lng'];
                $radius = 10; // 10km radius
                
                $query->whereBetween('latitude', [$lat - ($radius / 111), $lat + ($radius / 111)])
                      ->whereBetween('longitude', [$lng - ($radius / (111 * cos(deg2rad($lat)))), $lng + ($radius / (111 * cos(deg2rad($lat))))]);
            } else {
                // Fallback to text search
                $query->where(function($q) use ($location) {
                    $q->where('location', 'like', "%{$location}%")
                      ->orWhere('title', 'like', "%{$location}%");
                });
            }
        }

        // Price filters
        if ($request->filled('minPrice')) {
            $query->where('price', '>=', (float)$request->input('minPrice'));
        }
        if ($request->filled('maxPrice')) {
            $query->where('price', '<=', (float)$request->input('maxPrice'));
        }

        // Beds filter (if you have a beds field, otherwise skip)
        // if ($request->filled('beds')) {
        //     $query->where('beds', '>=', (int)$request->input('beds'));
        // }

        // Type filter (if you have a type field)
        // if ($request->filled('type')) {
        //     $query->where('type', $request->input('type'));
        // }

        // Only available properties
        if ($request->filled('free')) {
            $query->where('free', filter_var($request->input('free'), FILTER_VALIDATE_BOOLEAN));
        }

        $logements = $query->whereNotNull('latitude')
                          ->whereNotNull('longitude')
                          ->where('latitude', '!=', 0)
                          ->where('longitude', '!=', 0)
                          ->with(['agency:id,name', 'category:id,name'])
                          ->get()
                          ->map(function($logement) {
                              return [
                                  'id' => $logement->_id,
                                  '_id' => $logement->_id,
                                  'title' => $logement->title,
                                  'address' => $logement->location ?? '',
                                  'location' => $logement->location ?? '',
                                  'price' => $logement->price ?? 0,
                                  'lat' => (float)$logement->latitude,
                                  'lng' => (float)$logement->longitude,
                                  'latitude' => (float)$logement->latitude,
                                  'longitude' => (float)$logement->longitude,
                                  'image' => $logement->image ?? null,
                                  'mainImage' => $logement->image ?? null,
                                  'coverImage' => $logement->image ?? null,
                                  'beds' => $logement->beds ?? $logement->chambres ?? 0,
                                  'baths' => $logement->baths ?? $logement->sallesDeBain ?? 0,
                                  'surface' => $logement->surface ?? 0,
                                  'free' => $logement->free ?? true,
                                  'type' => $logement->type ?? 'house',
                                  'category' => $logement->category ? [
                                      'id' => $logement->category->_id,
                                      'name' => $logement->category->name,
                                  ] : null,
                                  'agency' => $logement->agency ? [
                                      'id' => $logement->agency->_id,
                                      'name' => $logement->agency->name,
                                  ] : null,
                              ];
                          });

        return response()->json([
            'success' => true,
            'properties' => $logements,
            'count' => $logements->count(),
        ]);
    }

    /**
     * Get all logements with coordinates for map display
     * Returns all logements that have latitude and longitude
     */
    public function getAllWithCoordinates(): JsonResponse
    {
        try {
            $logements = Logement::query()
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->where('latitude', '!=', 0)
                ->where('longitude', '!=', 0)
                ->with(['agency:id,name', 'category:id,name'])
                ->get()
                ->map(function($logement) {
                    return [
                        'id' => $logement->_id,
                        'title' => $logement->title,
                        'description' => $logement->description ?? '',
                        'address' => $logement->location ?? '',
                        'price' => $logement->price ?? 0,
                        'lat' => (float)$logement->latitude,
                        'lng' => (float)$logement->longitude,
                        'image' => $logement->image ?? null,
                        'free' => $logement->free ?? true,
                        'surface' => $logement->surface ?? 0,
                        'category' => $logement->category ? [
                            'id' => $logement->category->_id,
                            'name' => $logement->category->name,
                        ] : null,
                        'agency' => $logement->agency ? [
                            'id' => $logement->agency->_id,
                            'name' => $logement->agency->name,
                        ] : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'properties' => $logements,
                'count' => $logements->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('LogementController.getAllWithCoordinates error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des logements',
                'properties' => [],
                'count' => 0,
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource. (facultatif)
     */
    public function create()
    {
        return view('logement::create');
    }

    /**
     * Store a newly created resource in storage.
     */
  public function store(Request $request): JsonResponse
{
    // 1) Validation de base
    $validated = $request->validate([
        'title'       => ['required','string','max:255'],
        'description' => ['nullable','string'],
        'price'       => ['required','numeric','min:0'],

        // ⚠️ on ne met plus de "exists" ici
        'category_id' => ['required','string'],
        'agency_id'   => ['required','string'],

        'latitude'    => ['nullable','numeric','between:-90,90'],
        'longitude'   => ['nullable','numeric','between:-180,180'],
        'location'    => ['nullable','string','max:255'],
        'surface'     => ['nullable','integer','min:0'],
        'floor'       => ['nullable','integer'],
        'free'        => ['sometimes','boolean'],
        
        // ✅ nouveaux champs image
        'image'       => ['nullable','string','max:1000'],
        'images'      => ['nullable','array'],
        'images.*'    => ['nullable','string','max:1000'],
    ]);

    // 2) Vérifier manuellement que l'agence existe dans MongoDB
    $agency = Agency::where('_id', $validated['agency_id'])->first();
    if (!$agency) {
        return response()->json([
            'message' => 'The selected agency id is invalid.',
            'errors'  => [
                'agency_id' => ['The selected agency id is invalid.'],
            ],
        ], 422);
    }

    // 3) Vérifier manuellement que la catégorie existe (si tu as le module Category)
    $category = Category::where('_id', $validated['category_id'])->first();
    if (!$category) {
        return response()->json([
            'message' => 'The selected category id is invalid.',
            'errors'  => [
                'category_id' => ['The selected category id is invalid.'],
            ],
        ], 422);
    }

    // 4) Par défaut, le logement est libre
    if (!array_key_exists('free', $validated)) {
        $validated['free'] = true;
    }

    // 5) (Optionnel) si pas de lat/lng mais une adresse, on géocode
    if (
        (!isset($validated['latitude']) || !isset($validated['longitude']))
        && $request->filled('location')
    ) {
        $geo = app(GeocodingService::class)->geocode($request->input('location'));
        if ($geo) {
            $validated['latitude']  = $geo['lat'];
            $validated['longitude'] = $geo['lng'];
        }
    }

    // 6) Création du logement
    $logement = Logement::create($validated);

    return response()->json([
        'success'  => true,
        'message'  => 'Logement créé avec succès',
        'logement' => $logement,
    ], 201);
}
    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            // Try to find by _id (MongoDB format) or id
            $logement = Logement::where('_id', $id)->first();
            
            // If not found by _id, try to find by id field (if exists)
            if (!$logement) {
                $logement = Logement::where('id', $id)->first();
            }
            
            if (!$logement) {
                return response()->json([
                    'success' => false,
                    'error' => 'Logement non trouvé',
                    'message' => 'Le logement avec l\'ID ' . $id . ' n\'existe pas.'
                ], 404);
            }

            // Load relationships manually to avoid namespace resolution issues
            $agency = null;
            if ($logement->agency_id) {
                $agencyModel = \Modules\Agency\App\Models\Agency::find($logement->agency_id);
                if ($agencyModel) {
                    $agency = [
                        '_id' => $agencyModel->_id,
                        'id' => $agencyModel->_id,
                        'name' => $agencyModel->name ?? null,
                        'city' => $agencyModel->city ?? null,
                        'address' => $agencyModel->address ?? null,
                        'phone' => $agencyModel->phone ?? null,
                        'email' => $agencyModel->email ?? null,
                        'location' => $agencyModel->location ?? null,
                    ];
                }
            }

            $category = null;
            if ($logement->category_id) {
                $categoryModel = \Modules\Category\App\Models\Category::find($logement->category_id);
                if ($categoryModel) {
                    $category = [
                        '_id' => $categoryModel->_id,
                        'id' => $categoryModel->_id,
                        'name' => $categoryModel->name ?? null,
                        'description' => $categoryModel->description ?? null,
                    ];
                }
            }

            // Format the response with all necessary data
            $formattedLogement = [
                '_id' => $logement->_id,
                'id' => $logement->_id, // Also include as 'id' for frontend compatibility
                'title' => $logement->title,
                'description' => $logement->description ?? '',
                'price' => $logement->price ?? 0,
                'location' => $logement->location ?? '',
                'address' => $logement->location ?? '', // Alias for frontend compatibility
                'latitude' => $logement->latitude ?? null,
                'longitude' => $logement->longitude ?? null,
                'surface' => $logement->surface ?? null,
                'floor' => $logement->floor ?? null,
                'free' => $logement->free ?? true,
                'image' => $logement->image ?? null,
                'mainImage' => $logement->image ?? null, // Alias for frontend compatibility
                'gallery' => $logement->gallery ?? ($logement->image ? [$logement->image] : []),
                'beds' => $logement->beds ?? null,
                'baths' => $logement->baths ?? null,
                'category' => $category,
                'agency' => $agency,
                'agent' => null, // Can be populated if you have agent relationship
                'features' => $logement->features ?? [],
                'type' => $logement->type ?? 'house',
                'chambres' => $logement->chambres ?? $logement->beds ?? null,
                'sallesDeBain' => $logement->sallesDeBain ?? $logement->baths ?? null,
                'images' => is_array($logement->images) ? $logement->images : ($logement->image ? [$logement->image] : []),
                'photos' => is_array($logement->photos) ? $logement->photos : ($logement->image ? [$logement->image] : []),
                'created_at' => $logement->created_at ?? null,
                'updated_at' => $logement->updated_at ?? null,
            ];

            return response()->json([
                'success' => true,
                'logement' => $formattedLogement,
            ]);
        } catch (\Throwable $e) {
            Log::error('LogementController.show error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'id' => $id,
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la récupération du logement',
                'message' => config('app.debug') ? $e->getMessage() : 'Erreur serveur'
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource. (facultatif)
     */
    public function edit(string $id)
    {
        return view('logement::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $logement = Logement::find($id);
        if (!$logement) {
            return response()->json(['error' => 'Logement non trouvé'], 404);
        }

        $validated = $request->validate([
            'title'       => ['sometimes','string','max:255'],
            'description' => ['sometimes','nullable','string'],
            'price'       => ['sometimes','numeric','min:0'],
            'category_id' => ['sometimes','string','exists:categorys,_id'],
            'agency_id'   => ['sometimes','string','exists:agencies,_id'],
            'latitude'    => ['sometimes','nullable','numeric','between:-90,90'],
            'longitude'   => ['sometimes','nullable','numeric','between:-180,180'],
            'location'    => ['sometimes','nullable','string','max:255'],
            'surface'     => ['sometimes','nullable','integer','min:0'],
            'floor'       => ['sometimes','nullable','integer'],
            'free'        => ['sometimes','boolean'],
        ]);

        $logement->update($validated);

        return response()->json([
            'success'  => true,
            'message'  => 'Logement mis à jour avec succès',
            'logement' => $logement,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $logement = Logement::find($id);
        if (!$logement) {
            return response()->json(['error' => 'Logement non trouvé'], 404);
        }

        $logement->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logement supprimé avec succès',
        ]);
    }
}
<?php

namespace Modules\Logement\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Modules\Logement\App\Models\Logement;


class LogementController extends Controller
{
   /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success'   => true,
            'logements' => Logement::orderBy('title')->get(),
        ]);
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
        $validated = $request->validate([
            'title'       => ['required','string','max:255'],
            'description' => ['nullable','string'],
            'price'       => ['required','numeric','min:0'],
            'category_id' => ['required','string','exists:categorys,_id'], // ton modèle Category => table 'categorys'
            'agency_id'   => ['required','string','exists:agencies,_id'],
            'latitude'    => ['nullable','numeric','between:-90,90'],
            'longitude'   => ['nullable','numeric','between:-180,180'],
            'location'    => ['nullable','string','max:255'],
            'surface'     => ['nullable','integer','min:0'],
            'floor'       => ['nullable','integer'],
            'free'        => ['sometimes','boolean'],
        ]);

        // par défaut, libre
        if (!array_key_exists('free', $validated)) {
            $validated['free'] = true;
        }

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
        $logement = Logement::find($id);
        if (!$logement) {
            return response()->json(['error' => 'Logement non trouvé'], 404);
        }

        return response()->json([
            'success'  => true,
            'logement' => $logement, // tu peux faire ->load('agency','category') si tu veux
        ]);
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
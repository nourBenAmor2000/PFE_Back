<?php

namespace Modules\Review\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Modules\Review\App\Models\Review;

class ReviewController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'reviews' => Review::orderBy('created_at', 'desc')->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource. (facultatif)
     */
    public function create()
    {
        return view('review::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id'   => ['required','string','exists:clients,_id'],
            'logement_id' => ['required','string','exists:logements,_id'],
            'comment'     => ['nullable','string','max:2000'],
            'rating'      => ['required','integer','between:1,5'],
        ]);

        // (Option utile) Empêcher un doublon client/logement
        $exists = Review::where('client_id', $validated['client_id'])
            ->where('logement_id', $validated['logement_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => 'Ce client a déjà laissé une review pour ce logement.'
            ], 409);
        }

        $review = Review::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Review créée avec succès',
            'review'  => $review,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $review = Review::find($id);
        if (!$review) {
            return response()->json(['error' => 'Review non trouvée'], 404);
        }

        return response()->json([
            'success' => true,
            'review'  => $review,
        ]);
    }

    /**
     * Show the form for editing the specified resource. (facultatif)
     */
    public function edit(string $id)
    {
        return view('review::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $review = Review::find($id);
        if (!$review) {
            return response()->json(['error' => 'Review non trouvée'], 404);
        }

        $validated = $request->validate([
            'client_id'   => ['sometimes','string','exists:clients,_id'],
            'logement_id' => ['sometimes','string','exists:logements,_id'],
            'comment'     => ['sometimes','nullable','string','max:2000'],
            'rating'      => ['sometimes','integer','between:1,5'],
        ]);

        // Vérifie l’unicité (client_id + logement_id) si l’un des deux change
        $newClient   = $validated['client_id']   ?? $review->client_id;
        $newLogement = $validated['logement_id'] ?? $review->logement_id;

        $dup = Review::where('client_id', $newClient)
            ->where('logement_id', $newLogement)
            ->where('_id', '!=', $review->_id) // Mongo : clé _id
            ->exists();

        if ($dup) {
            return response()->json([
                'error' => 'Ce client a déjà une review pour ce logement.'
            ], 409);
        }

        $review->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Review mise à jour avec succès',
            'review'  => $review,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $review = Review::find($id);
        if (!$review) {
            return response()->json(['error' => 'Review non trouvée'], 404);
        }

        $review->delete();

        return response()->json([
            'success' => true,
            'message' => 'Review supprimée avec succès',
        ]);
    }
}
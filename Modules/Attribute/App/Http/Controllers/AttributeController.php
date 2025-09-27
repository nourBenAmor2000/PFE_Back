<?php

namespace Modules\Attribute\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Modules\Attribute\App\Models\Attribute;

class AttributeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success'    => true,
            'attributes' => Attribute::orderBy('name')->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     * (facultatif, pour vue blade si tu en as besoin)
     */
    public function create()
    {
        return view('attribute::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required','string','max:255','unique:attributes,name'],
            'type' => ['required', Rule::in(['bool','list','text','number'])],
        ]);

        $attribute = Attribute::create($validated);

        return response()->json([
            'success'   => true,
            'message'   => 'Attribut créé avec succès',
            'attribute' => $attribute,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        $attribute = Attribute::find($id);
        if (!$attribute) {
            return response()->json(['error' => 'Attribut non trouvé'], 404);
        }

        return response()->json([
            'success'   => true,
            'attribute' => $attribute,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     * (facultatif, pour vue blade si tu en as besoin)
     */
    public function edit($id)
    {
        return view('attribute::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $attribute = Attribute::find($id);
        if (!$attribute) {
            return response()->json(['error' => 'Attribut non trouvé'], 404);
        }

        $validated = $request->validate([
            'name' => [
                'sometimes','string','max:255',
                Rule::unique('attributes','name')->ignore($attribute->_id ?? $attribute->id, '_id'),
            ],
            'type' => ['sometimes', Rule::in(['bool','list','text','number'])],
        ]);

        $attribute->update($validated);

        return response()->json([
            'success'   => true,
            'message'   => 'Attribut mis à jour avec succès',
            'attribute' => $attribute,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        $attribute = Attribute::find($id);
        if (!$attribute) {
            return response()->json(['error' => 'Attribut non trouvé'], 404);
        }

        $attribute->delete();

        return response()->json([
            'success' => true,
            'message' => 'Attribut supprimé avec succès',
        ]);
    }
}
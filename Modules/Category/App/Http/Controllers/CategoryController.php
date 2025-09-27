<?php

namespace Modules\Category\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Modules\Category\App\Models\Category;

class CategoryController extends Controller
{
     /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success'     => true,
            'categories'  => Category::orderBy('name')->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     * (facultatif, si tu as des vues Blade)
     */
    public function create()
    {
        return view('category::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required','string','max:255','unique:categorys,name'],
        ]);

        $category = Category::create($validated);

        return response()->json([
            'success'   => true,
            'message'   => 'Catégorie créée avec succès',
            'category'  => $category,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $category = Category::find($id);
        if (!$category) {
            return response()->json(['error' => 'Catégorie non trouvée'], 404);
        }

        return response()->json([
            'success'  => true,
            'category' => $category,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     * (facultatif)
     */
    public function edit(string $id)
    {
        return view('category::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $category = Category::find($id);
        if (!$category) {
            return response()->json(['error' => 'Catégorie non trouvée'], 404);
        }

        $validated = $request->validate([
            'name' => [
                'sometimes','string','max:255',
                // Mongo: ignorer via clé _id ; si SQL: $category->id
                Rule::unique('categorys','name')->ignore($category->_id ?? $category->id, '_id'),
            ],
        ]);

        $category->update($validated);

        return response()->json([
            'success'  => true,
            'message'  => 'Catégorie mise à jour avec succès',
            'category' => $category,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $category = Category::find($id);
        if (!$category) {
            return response()->json(['error' => 'Catégorie non trouvée'], 404);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Catégorie supprimée avec succès',
        ]);
    }
}
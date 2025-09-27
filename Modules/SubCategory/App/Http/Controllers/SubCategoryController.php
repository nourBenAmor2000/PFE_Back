<?php

namespace Modules\SubCategory\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Modules\SubCategory\App\Models\SubCategory;

class SubCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success'       => true,
            'subcategories' => SubCategory::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('subcategory::create');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required','string','max:255',
                Rule::unique('subcategorys','name')->where(fn($q)=>$q->where('category_id',$request->category_id))
            ],
            'category_id' => ['required','string','exists:categorys,_id'], // table Category = "categorys"
        ]);

        $sub = SubCategory::create($validated);

        return response()->json([
            'success'     => true,
            'message'     => 'Sous-catégorie créée avec succès',
            'subcategory' => $sub,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $sub = SubCategory::find($id);
        if (!$sub) return response()->json(['error'=>'Sous-catégorie non trouvée'], 404);

        return response()->json(['success'=>true,'subcategory'=>$sub]);
    }

    public function edit(string $id)
    {
        return view('subcategory::edit');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $sub = SubCategory::find($id);
        if (!$sub) return response()->json(['error'=>'Sous-catégorie non trouvée'], 404);

        $categoryId = $request->input('category_id', $sub->category_id);

        $validated = $request->validate([
            'name' => ['sometimes','string','max:255',
                Rule::unique('subcategorys','name')
                    ->ignore($sub->_id ?? $sub->id, '_id')
                    ->where(fn($q)=>$q->where('category_id', $categoryId))
            ],
            'category_id' => ['sometimes','string','exists:categorys,_id'],
        ]);

        $sub->update($validated);

        return response()->json([
            'success'     => true,
            'message'     => 'Sous-catégorie mise à jour avec succès',
            'subcategory' => $sub,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $sub = SubCategory::find($id);
        if (!$sub) return response()->json(['error'=>'Sous-catégorie non trouvée'], 404);

        $sub->delete();

        return response()->json(['success'=>true,'message'=>'Sous-catégorie supprimée avec succès']);
    }
}
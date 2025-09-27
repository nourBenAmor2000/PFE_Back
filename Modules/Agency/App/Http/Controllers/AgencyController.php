<?php

namespace Modules\Agency\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Agency\App\Models\Agency;
use Illuminate\Http\JsonResponse;


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
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:agencies',
            'phone' => 'nullable|string|max:20',
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
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:agencies,email,' . $agency->id,
            'phone' => 'nullable|string|max:20',
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
   

    

    

    

    
}

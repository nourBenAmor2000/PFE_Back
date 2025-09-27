<?php

namespace Modules\Contract\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;
use Modules\Contract\App\Models\Contract;

class ContractController extends Controller
{
   /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success'   => true,
            'contracts' => Contract::orderBy('start_date', 'desc')->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource. (facultatif pour Blade)
     */
    public function create()
    {
        return view('contract::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id'   => ['required','string','exists:clients,_id'],
            'logement_id' => ['required','string','exists:logements,_id'],
            'start_date'  => ['required','date'],
            'end_date'    => ['required','date','after:start_date'],
            'amount'      => ['required','numeric','min:0'],
        ]);

        $contract = Contract::create($validated);

        return response()->json([
            'success'  => true,
            'message'  => 'Contrat créé avec succès',
            'contract' => $contract,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $contract = Contract::find($id);
        if (!$contract) {
            return response()->json(['error' => 'Contrat non trouvé'], 404);
        }

        return response()->json([
            'success'  => true,
            'contract' => $contract,
        ]);
    }

    /**
     * Show the form for editing the specified resource. (facultatif)
     */
    public function edit(string $id)
    {
        return view('contract::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $contract = Contract::find($id);
        if (!$contract) {
            return response()->json(['error' => 'Contrat non trouvé'], 404);
        }

        $validated = $request->validate([
            'client_id'   => ['sometimes','string','exists:clients,_id'],
            'logement_id' => ['sometimes','string','exists:logements,_id'],
            'start_date'  => ['sometimes','date'],
            'end_date'    => ['sometimes','date'],
            'amount'      => ['sometimes','numeric','min:0'],
        ]);

        // Vérifier la cohérence des dates même si une seule est envoyée
        $newStart = isset($validated['start_date'])
            ? Carbon::parse($validated['start_date'])
            : ($contract->start_date ? Carbon::parse($contract->start_date) : null);

        $newEnd = isset($validated['end_date'])
            ? Carbon::parse($validated['end_date'])
            : ($contract->end_date ? Carbon::parse($contract->end_date) : null);

        if ($newStart && $newEnd && $newEnd->lt($newStart)) {
            return response()->json([
                'error' => 'La date de fin doit être postérieure à la date de début.'
            ], 422);
        }

        $contract->update($validated);

        return response()->json([
            'success'  => true,
            'message'  => 'Contrat mis à jour avec succès',
            'contract' => $contract,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $contract = Contract::find($id);
        if (!$contract) {
            return response()->json(['error' => 'Contrat non trouvé'], 404);
        }

        // Si tu veux empêcher la suppression quand un paiement existe, décommente:
        // if ($contract->payment()->exists()) {
        //     return response()->json([
        //         'error' => 'Impossible de supprimer: un paiement lié existe.'
        //     ], 409);
        // }

        $contract->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contrat supprimé avec succès',
        ]);
    }
}
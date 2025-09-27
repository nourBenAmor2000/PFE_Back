<?php

namespace Modules\PaymentContracts\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Modules\PaymentContracts\App\Models\PaymentContracts; // <-- bon modèle (pluriel)
use Modules\Contract\App\Models\Contract; // si tu veux vérifier/charger la relation

class PaymentContractsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success'  => true,
            'payments' => PaymentContracts::orderBy('date_paiement', 'desc')->get(),
        ]);
    }

    public function create()
    {
        return view('contract::payments.create');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // 'contract_id'           => ['required','string','regex:/^[a-f0-9]{24}$/i','exists:mongodb.contracts,_id'],
            'montant'               => ['required','numeric','min:0'],
            'methode_paiement'      => ['required','string','max:50'], // ex: card, cash, transfer
            'statut'                => ['required', Rule::in(['PENDING','PAID','FAILED','REFUNDED'])],
            'date_paiement'         => ['required','date'],
            'reference_transaction' => ['nullable','string','max:120'],
        ]);

        $payment = PaymentContracts::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Paiement de contrat créé avec succès',
            'payment' => $payment,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $payment = PaymentContracts::find($id);
        if (!$payment) {
            return response()->json(['error' => 'Paiement non trouvé'], 404);
        }

        return response()->json([
            'success' => true,
            'payment' => $payment,
        ]);
    }

    public function edit(string $id)
    {
        return view('contract::payments.edit');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $payment = PaymentContracts::find($id);
        if (!$payment) {
            return response()->json(['error' => 'Paiement non trouvé'], 404);
        }

        $validated = $request->validate([
            'contract_id'           => ['sometimes','string','regex:/^[a-f0-9]{24}$/i','exists:mongodb.contracts,_id'],
            'montant'               => ['sometimes','numeric','min:0'],
            'methode_paiement'      => ['sometimes','string','max:50'],
            'statut'                => ['sometimes', Rule::in(['PENDING','PAID','FAILED','REFUNDED'])],
            'date_paiement'         => ['sometimes','date'],
            'reference_transaction' => ['sometimes','nullable','string','max:120'],
        ]);

        $payment->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Paiement mis à jour avec succès',
            'payment' => $payment,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $payment = PaymentContracts::find($id);
        if (!$payment) {
            return response()->json(['error' => 'Paiement non trouvé'], 404);
        }

        $payment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Paiement supprimé avec succès',
        ]);
    }
}

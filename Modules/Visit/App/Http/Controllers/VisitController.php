<?php

namespace Modules\Visit\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Modules\Visit\App\Models\Visit;

class VisitController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'visits'  => Visit::orderBy('visit_date', 'desc')->get(),
        ]);
    }

    public function create()
    {
        return view('visit::create');
    }

    public function store(Request $request): JsonResponse
    {
        $v = $request->validate([
            'client_id'   => ['required','string','exists:clients,_id'],
            'logement_id' => ['required','string','exists:logements,_id'],
            'visit_date'  => ['required','date'], // ex: "2025-10-01 14:00:00"
        ]);

        // (Option utile) empêcher un doublon exact client/logement/date
        $exists = Visit::where('client_id', $v['client_id'])
            ->where('logement_id', $v['logement_id'])
            ->where('visit_date', $v['visit_date'])
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => 'Une visite existe déjà pour ce client, ce logement et cette date.'
            ], 409);
        }

        $visit = Visit::create($v);

        return response()->json([
            'success' => true,
            'message' => 'Visite créée avec succès',
            'visit'   => $visit,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $visit = Visit::find($id);
        if (!$visit) return response()->json(['error'=>'Visite non trouvée'], 404);

        return response()->json(['success'=>true,'visit'=>$visit]);
    }

    public function edit(string $id)
    {
        return view('visit::edit');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $visit = Visit::find($id);
        if (!$visit) return response()->json(['error'=>'Visite non trouvée'], 404);

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
                'error' => 'Une visite identique existe déjà.'
            ], 409);
        }

        $visit->update($v);

        return response()->json([
            'success' => true,
            'message' => 'Visite mise à jour avec succès',
            'visit'   => $visit,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $visit = Visit::find($id);
        if (!$visit) return response()->json(['error'=>'Visite non trouvée'], 404);

        $visit->delete();

        return response()->json(['success'=>true,'message'=>'Visite supprimée avec succès']);
    }

    public function indexByAgency(): \Illuminate\Http\JsonResponse
{
    $agencyId = auth('agent')->user()->agency_id;

    $visits = \Modules\Visit\App\Models\Visit::whereHas('logement', function($q) use($agencyId) {
        $q->where('agency_id', $agencyId);
    })->get();

    return response()->json(['success'=>true, 'visits'=>$visits]);
}

public function showScoped($id): \Illuminate\Http\JsonResponse
{
    $agencyId = auth('agent')->user()->agency_id;

    $visit = \Modules\Visit\App\Models\Visit::where('_id', $id)
        ->whereHas('logement', function($q) use($agencyId) {
            $q->where('agency_id', $agencyId);
        })->first();

    if (!$visit) return response()->json(['error'=>'Visite non trouvée'],404);

    return response()->json(['success'=>true, 'visit'=>$visit]);
}

}
<?php

use Illuminate\Support\Facades\Route;
use Modules\Visit\App\Http\Controllers\VisitController;

/*
|--------------------------------------------------------------------------
| Routes VISITS
|--------------------------------------------------------------------------
|
| - Admin global : CRUD complet sur toutes les visites
| - Admin d'agence : lecture seule, filtrée par son agence
|
*/

// 🔹 Admin global : full CRUD sur toutes les visites
Route::middleware(['auth:admin', 'admin.role:admin_global'])->group(function () {
    Route::apiResource('visits', VisitController::class);
});

// 🔹 Admin d'agence : lecture seule, visites filtrées par agence
Route::middleware(['auth:agent', 'agent.role:admin_agence'])->group(function () {
    // index filtré par agence
    Route::get('visits', [VisitController::class, 'indexByAgency']);
    // détail sécurisé (vérifie que la visite appartient bien à l’agence)
    Route::get('visits/{visit}', [VisitController::class, 'showScoped']);
});

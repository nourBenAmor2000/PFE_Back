<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Agency\App\Http\Controllers\AgencyController;
use Modules\Agent\App\Http\Controllers\AgentController;
use Modules\Visit\App\Http\Controllers\VisitController;

/*
    |--------------------------------------------------------------------------
    | API Routes
    |--------------------------------------------------------------------------
    |
    | Here is where you can register API routes for your application. These
    | routes are loaded by the RouteServiceProvider within a group which
    | is assigned the "api" middleware group. Enjoy building your API!
    |
*/

// Route::middleware(['auth:sanctum'])->prefix('v1')->name('api.')->group(function () {
//     Route::get('agency', fn (Request $request) => $request->user())->name('agency');
// });
Route::middleware(['auth:agent', 'agent.role:admin_agence,rh'])->prefix('v1')->group(function () {
    Route::apiResource('agencies', AgencyController::class);
});

Route::prefix('admin-agency')->group(function () {
    // Auth
    Route::post('/register', [AgencyController::class, 'register']);
    Route::post('/login',    [AgencyController::class, 'login']);

    Route::middleware('auth:agent')->group(function () {
        Route::get('/me',      [AgencyController::class, 'me']);
        Route::post('/logout', [AgencyController::class, 'logout']);
        Route::post('/refresh',[AgencyController::class, 'refresh']);
    });
});

/** ADMIN AGENCE : CRUD + Agents + Visites */
Route::middleware(['auth:agent', 'agent.role:admin_agence'])->group(function () {
    // Agency CRUD (scopé à SA propre agence)
    Route::get('/agency',        [AgencyController::class, 'myAgencyIndex']);  // renvoie sa propre agence
    Route::get('/agency/{id}',   [AgencyController::class, 'showScoped']);     // vérifie même agency_id
    Route::post('/agency',       [AgencyController::class, 'storeScoped']);    // si tu autorises la création
    Route::put('/agency/{id}',   [AgencyController::class, 'updateScoped']);
    Route::delete('/agency/{id}',[AgencyController::class, 'destroyScoped']);

    // Agents CRUD (de son agence)
    Route::get('/agents',            [AgentController::class, 'listAgents']);
    Route::post('/agents',           [AgentController::class, 'store']);
    Route::get('/agents/{id}',       [AgentController::class, 'show']);
    Route::put('/agents/{id}',       [AgentController::class, 'updateAgent']);
    Route::delete('/agents/{id}',    [AgentController::class, 'destroyAgent']);

    // Visites (lecture seule)
    Route::get('/visits',        [VisitController::class, 'indexByAgency']);
    Route::get('/visits/{id}',   [VisitController::class, 'showScoped']);
});


<?php

use Illuminate\Support\Facades\Route;
use Modules\Agent\App\Http\Controllers\AgentController;
// Public route to get agencies list (for registration)
// Vérification email agent par code (PUBLIC)
Route::post('/agent/verify-code', [AgentController::class, 'verifyCode']);
Route::post('/agent/register', [AgentController::class, 'register']);
Route::post('/agent/verify-code', [AgentController::class, 'verifyCode']);
Route::post('/agent/login',    [AgentController::class, 'login']);
Route::prefix('agent')->group(function() {
    // Public routes
    Route::post('/register', [AgentController::class, 'register']);
    Route::post('/login', [AgentController::class, 'login'])->name('api.agent.login');;
      

     // Mot de passe oublié AGENT
    Route::post('/password/email', [AgentController::class, 'sendResetLinkEmail'])
        ->name('agent.password.email');

    // ✅ Reset du mot de passe (form + token)
    Route::post('/password/reset', [AgentController::class, 'resetPassword'])
        ->name('agent.password.update');

    // Protected routes
    Route::middleware('auth:agent')->group(function() {
        Route::get('/me', [AgentController::class, 'me']);
        Route::post('/logout', [AgentController::class, 'logout']);
        Route::post('/refresh', [AgentController::class, 'refresh']);
  
        Route::get('/profile', [AgentController::class, 'showProfile']); // Voir le profil
        Route::put('/profile/update', [AgentController::class, 'updateProfile']); // Modifier le profil
        Route::delete('/profile/delete', [AgentController::class, 'deleteProfile']); // Supprimer le compte
     
        
    });
    // Routes de vérification communes
Route::prefix('email')->group(function() {
    // Vérification
    Route::get('/verify/{id}/{hash}', 'Auth\VerificationController@verify')
        ->middleware(['signed'])
        ->name('verification.verify');
    
    // Renvoi du lien
    Route::post('/verify/resend', 'Auth\VerificationController@resend')
        ->middleware(['auth:api'])
        ->name('verification.resend');
});
Route::get('/agent/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $user = Agent::findOrFail($request->route('id'));

    if (! hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
        abort(403, 'Invalid verification link.');
    }

    if ($user->hasVerifiedEmail()) {
        return response()->json(['message' => 'Email already verified.']);
    }

    $user->markEmailAsVerified();

    return response()->json(['message' => 'Email verified successfully. You can now login.']);
})->middleware(['signed'])->name('agent.verification.verify');
});

// Route::middleware(['auth:agent', 'agent.role:admin_agence,rh'])->group(function () {
//     Route::get('/agents', [AgentController::class, 'listAgents']);
//     Route::put('/agents/{id}', [AgentController::class, 'updateAgent']);
//     Route::delete('/agents/{id}', [AgentController::class, 'destroyAgent']);
// });

/**
     * ADMIN AGENCE ou RH : CRUD sur les comptes Agents
     */
    // Route::middleware(['auth:agent', 'agent.role:admin_agence,rh'])->group(function () {
    //     Route::get('/agents', [AgentController::class, 'listAgents']);
    //     Route::post('/agents', [AgentController::class, 'store']);
    //     Route::get('/agents/{id}', [AgentController::class, 'show']);
    //     Route::put('/agents/{id}', [AgentController::class, 'updateAgent']);
    //     Route::delete('/agents/{id}', [AgentController::class, 'destroyAgent']);
    // });

    /**
     * AGENT PERSONNEL : CRUD Logement, Contrat, Visit
     * Uses normalized role values: agent (stored in DB)
     */
    Route::middleware(['auth:agent', 'agent.role:agent'])->group(function () {

        // Logement
        Route::apiResource('logements', LogementController::class);

        // Contrat
        Route::apiResource('contracts', ContractController::class);

        // Visit
        Route::apiResource('visits', VisitController::class);
        
        Route::get('profile', [AgentController::class, 'show']);
        Route::put('profile/update', [AgentController::class, 'update']);
    });
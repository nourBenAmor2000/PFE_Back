<?php

use Modules\Client\App\Http\Controllers\Auth\{
    ForgotPasswordController,
    ResetPasswordController,
    VerificationController
};
use Modules\Client\App\Http\Controllers\ClientController;

Route::prefix('client')->group(function() {
     Route::get('/clients', [ClientController::class, 'index']);   // Lister tous les clients
    Route::post('/clients', [ClientController::class, 'store']);  // Créer un client
    Route::get('/clients/{id}', [ClientController::class, 'show']); // Voir un client
    Route::put('/clients/{id}', [ClientController::class, 'update']); // Modifier un client
    Route::delete('/clients/{id}', [ClientController::class, 'destroy']); // Supprimer un client
    // Public routes
    Route::post('/register', [ClientController::class, 'register']);
    Route::post('/login', [ClientController::class, 'login']);
    
    // Password reset routes
    Route::post('/password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])
         ->name('client.password.email');
    Route::post('/password/reset', [ResetPasswordController::class, 'reset'])
         ->name('client.password.update');
    Route::get('/password/reset/{token}', function ($token) {
        return response()->json(['token' => $token]);
    })->name('client.password.reset');

    Route::get('/client/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])
    ->name('client.password.reset');


    // Protected routes
    Route::middleware('auth:client')->group(function() {
        Route::get('/me', [ClientController::class, 'me']);
        Route::post('/logout', [ClientController::class, 'logout']);
        Route::post('/refresh', [ClientController::class, 'refresh']);
        Route::get('/profile', [ClientController::class, 'showProfile']);
        Route::put('/profile/update', [ClientController::class, 'updateProfile']);
        Route::delete('/profile/delete', [ClientController::class, 'deleteProfile']);
        
        // Verification
        Route::post('/email/resend', [VerificationController::class, 'resend'])
            ->name('client.verification.resend');
    });
    
    // Verification
    Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
        ->middleware(['signed'])
        ->name('client.verification.verify');
});
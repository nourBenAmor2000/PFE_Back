<?php

use Modules\Client\App\Http\Controllers\Auth\{
    ForgotPasswordController,
    ResetPasswordController,
    VerificationController
};
use Modules\Client\App\Http\Controllers\ClientController;







Route::prefix('client')->group(function() {

    // Auth public
    Route::post('/register', [ClientController::class, 'register']);
    Route::post('/login', [ClientController::class, 'login']);

    // ✅ Mot de passe oublié (email de reset)
    Route::post('/password/email', [ClientController::class, 'sendResetLinkEmail'])
        ->name('client.password.email');

    // ✅ Reset du mot de passe (form + token)
    Route::post('/password/reset', [ClientController::class, 'resetPassword'])
        ->name('client.password.update');

    // Routes protégées
    Route::middleware('auth:client')->group(function() {
        Route::get('/me', [ClientController::class, 'me']);
        Route::post('/logout', [ClientController::class, 'logout']);
        Route::post('/refresh', [ClientController::class, 'refresh']);
        Route::get('/profile', [ClientController::class, 'showProfile']);
        Route::put('/profile/update', [ClientController::class, 'updateProfile']);
        Route::delete('/profile/delete', [ClientController::class, 'deleteProfile']);

        Route::post('/email/resend', [VerificationController::class, 'resend'])
            ->name('client.verification.resend');
    });

    Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
        ->middleware(['signed'])
        ->name('client.verification.verify');
});

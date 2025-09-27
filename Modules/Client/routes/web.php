<?php
use Modules\Client\App\Http\Controllers\{
    ClientController,
    Auth\ForgotPasswordController,
    Auth\ResetPasswordController,
    Auth\VerificationController
};

Route::prefix('client')->group(function() {
     
    // Authentication
    Route::get('/login', [ClientController::class, 'showLoginForm'])->name('client.login');
    Route::post('/login', [ClientController::class, 'login']);
    
    // Password reset
    Route::get('/password/reset', [ForgotPasswordController::class, 'showLinkRequestForm'])
         ->name('client.password.request');
    Route::post('/password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])
         ->name('client.password.email');
    Route::get('/password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])
         ->name('client.password.reset');
    Route::post('/password/reset', [ResetPasswordController::class, 'reset'])
         ->name('client.password.update');

   
});
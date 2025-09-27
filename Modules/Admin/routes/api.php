<?php

use Illuminate\Support\Facades\Route;
use Modules\Admin\App\Http\Controllers\AdminController;

Route::prefix('admin')->group(function () {
    // Public routes
    Route::post('/login', [AdminController::class, 'login'])->name('api.admin.login');

    // Protected routes
    Route::middleware('auth:admin')->group(function () {
        Route::get('me', [AdminController::class, 'me']);
        Route::post('logout', [AdminController::class, 'logout']);
        Route::post('refresh', [AdminController::class, 'refresh']);
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
});
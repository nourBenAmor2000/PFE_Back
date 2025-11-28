<?php

use Illuminate\Support\Facades\Route;
use Modules\PaymentContracts\App\Http\Controllers\PaymentContractsController; // <-- PLURIEL partout

// Route::apiResource('payment-contracts', PaymentContractsController::class);

// Protected routes - require authentication and appropriate role
Route::middleware(['auth:admin,agent'])->group(function () {
    Route::apiResource('payment-contracts', PaymentContractsController::class);
});
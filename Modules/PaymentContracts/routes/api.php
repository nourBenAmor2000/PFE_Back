<?php

use Illuminate\Support\Facades\Route;
use Modules\PaymentContracts\App\Http\Controllers\PaymentContractsController; // <-- PLURIEL partout

Route::apiResource('payment-contracts', PaymentContractsController::class);

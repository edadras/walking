<?php

use App\Http\Controllers\PaymentCallbackController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Bank redirect after a rial payment (opened in the phone's browser).
Route::get('/payments/{gateway}/callback', PaymentCallbackController::class)
    ->whereIn('gateway', ['zarinpal'])->middleware('throttle:60,1')->name('payments.callback');

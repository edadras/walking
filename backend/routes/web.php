<?php

use App\Http\Controllers\PaymentCallbackController;
use App\Http\Controllers\Web\LandingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'home']);
Route::get('/r/{code}', [LandingController::class, 'referral'])->where('code', '[A-Za-z0-9]{4,12}')->middleware('throttle:60,1');
Route::get('/.well-known/assetlinks.json', [LandingController::class, 'assetLinks']);

// Bank redirect after a rial payment (opened in the phone's browser).
Route::get('/payments/{gateway}/callback', PaymentCallbackController::class)
    ->whereIn('gateway', ['zarinpal'])->middleware('throttle:60,1')->name('payments.callback');

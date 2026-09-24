<?php

use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\AdController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChallengeController;
use App\Http\Controllers\Api\V1\ConfigController;
use App\Http\Controllers\Api\V1\ContentController;
use App\Http\Controllers\Api\V1\CouponController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\GamificationController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\RewardController;
use App\Http\Controllers\Api\V1\SponsorOfferController;
use App\Http\Controllers\Api\V1\VisitController;
use App\Http\Controllers\Api\V1\WalkingSessionController;
use App\Http\Controllers\Api\V1\WalletController;
use Illuminate\Support\Facades\Route;

/*
| Middleware aliases (bootstrap/app.php):
|   signed  → request signed by the registered device's Keystore key
|   app     → token's device is active, user is active (updates last-seen)
*/

// Public
Route::get('config', ConfigController::class)->middleware('throttle:public');
Route::get('time', [DeviceController::class, 'time'])->middleware('throttle:public');
Route::get('pages/{slug}', [ContentController::class, 'page'])->middleware('throttle:public');
Route::get('faqs', [ContentController::class, 'faqs'])->middleware('throttle:public');

// Ad network server-to-server reward callbacks (HMAC per provider)
Route::post('webhooks/ads/{provider}', [AdController::class, 'webhook'])->middleware('throttle:public');

// Device registration (self-signed with the submitted key)
Route::post('devices/register', [DeviceController::class, 'register'])->middleware('throttle:device-register');

// OTP login (signed by a registered device)
Route::middleware(['signed.device', 'throttle:public'])->prefix('auth/otp')->group(function () {
    Route::post('request', [AuthController::class, 'requestOtp']);
    Route::post('verify', [AuthController::class, 'verifyOtp']);
});

// Authenticated
Route::middleware(['auth:sanctum', 'app', 'throttle:api'])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/devices', [AuthController::class, 'devices']);
    Route::delete('auth/devices/{device}', [AuthController::class, 'revokeDevice'])->middleware('signed.device');
    Route::put('devices/push-token', [DeviceController::class, 'updatePushToken']);

    Route::get('me', [ProfileController::class, 'show']);
    Route::patch('me', [ProfileController::class, 'update']);
    Route::post('me/avatar', [ProfileController::class, 'updateAvatar']);
    Route::patch('me/settings', [ProfileController::class, 'updateSettings']);
    Route::get('me/notification-preferences', [ProfileController::class, 'notificationPreferences']);
    Route::patch('me/notification-preferences', [ProfileController::class, 'updateNotificationPreferences']);
    Route::post('me/deletion-request', [ProfileController::class, 'requestDeletion'])->middleware('signed.device');
    Route::delete('me/deletion-request', [ProfileController::class, 'cancelDeletion']);

    Route::get('home', [ActivityController::class, 'home']);
    Route::get('activity/day', [ActivityController::class, 'day']);
    Route::get('activity/daily', [ActivityController::class, 'daily']);

    Route::get('walking-sessions', [WalkingSessionController::class, 'index']);
    Route::get('walking-sessions/{session}', [WalkingSessionController::class, 'show']);
    Route::middleware(['signed.device', 'throttle:sessions'])->group(function () {
        Route::post('walking-sessions', [WalkingSessionController::class, 'store']);
        Route::post('walking-sessions/batch', [WalkingSessionController::class, 'batch']);
    });

    Route::get('wallet', [WalletController::class, 'show']);
    Route::get('wallet/transactions', [WalletController::class, 'transactions']);
    Route::get('rewards', [RewardController::class, 'index']);
    Route::get('rewards/{reward}', [RewardController::class, 'show']);

    Route::get('health/summary', [HealthController::class, 'summary']);
    Route::get('activity/weekly-report', [HealthController::class, 'weeklyReport']);
    Route::get('health/water', [HealthController::class, 'water']);
    Route::post('health/water', [HealthController::class, 'addWater']);
    Route::delete('health/water/{log}', [HealthController::class, 'deleteWater']);

    Route::get('progress', [GamificationController::class, 'progress']);
    Route::get('achievements', [GamificationController::class, 'achievements']);
    Route::get('leaderboard', [GamificationController::class, 'leaderboard']);
    Route::get('referral', [GamificationController::class, 'referral']);

    Route::get('challenges', [ChallengeController::class, 'index']);
    Route::get('challenges/{challenge}', [ChallengeController::class, 'show']);
    Route::post('challenges/{challenge}/join', [ChallengeController::class, 'join'])->middleware('signed.device');

    Route::get('locations/nearby', [SponsorOfferController::class, 'nearby']);
    Route::get('campaigns/{campaign}', [SponsorOfferController::class, 'campaign']);
    Route::get('visits', [VisitController::class, 'index']);
    Route::get('visits/{visit}', [VisitController::class, 'show']);
    Route::middleware(['signed.device', 'throttle:visits'])->group(function () {
        Route::post('visits', [VisitController::class, 'store']);
        Route::post('visits/{visit}/ping', [VisitController::class, 'ping']);
        Route::post('visits/{visit}/qr', [VisitController::class, 'qr']);
    });

    Route::get('coupons', [CouponController::class, 'index']);
    Route::get('coupons/{userCoupon}', [CouponController::class, 'show']);
    Route::post('coupons/{coupon}/claim', [CouponController::class, 'claim'])->middleware('signed.device');

    Route::get('ads/placements/{key}', [AdController::class, 'placement'])->middleware('throttle:ads');
    Route::post('ads/events', [AdController::class, 'events'])->middleware('throttle:ads');
    Route::get('ads/rewarded', [AdController::class, 'rewardedStatus']);
    Route::middleware(['signed.device', 'throttle:ads'])->group(function () {
        Route::post('ads/rewarded/start', [AdController::class, 'startRewarded']);
        Route::post('ads/rewarded/{view}/complete', [AdController::class, 'completeRewarded']);
    });

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read', [NotificationController::class, 'read']);

    Route::post('analytics/events', [AnalyticsController::class, 'store'])->middleware('throttle:analytics');
});

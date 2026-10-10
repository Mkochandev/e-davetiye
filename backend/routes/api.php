<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\MeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/otp', [AuthController::class, 'requestOtp'])->middleware('throttle:otp-ip');
    Route::post('auth/verify', [AuthController::class, 'verify']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('me', [MeController::class, 'show']);
        Route::patch('me', [MeController::class, 'update']);

        Route::post('devices', [DeviceController::class, 'store']);
    });
});

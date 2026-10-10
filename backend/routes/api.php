<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\InboxController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\RecipientController;
use App\Http\Controllers\Api\V1\TemplateController;
use Illuminate\Support\Facades\Route;

// {event} yalnız oturumdaki kullanıcının sahibi olduğu etkinliğe çözülür; başkasınınki 404 (spec §6, §14).
// Bağlama, auth:sanctum'dan sonra çalışır (middleware önceliği).
Route::bind('event', fn (string $value) => request()->user()->events()->whereKey((int) $value)->firstOrFail());

// {envelope} yalnız kullanıcıya bağlı ve gönderilmiş zarfa çözülür (posta kutusu, spec §6.2).
Route::bind('envelope', fn (string $value) => request()->user()->receivedRecipients()->sent()->whereKey((int) $value)->firstOrFail());

Route::prefix('v1')->group(function () {
    Route::post('auth/otp', [AuthController::class, 'requestOtp'])->middleware('throttle:otp-ip');
    Route::post('auth/verify', [AuthController::class, 'verify']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('me', [MeController::class, 'show']);
        Route::patch('me', [MeController::class, 'update']);

        Route::post('devices', [DeviceController::class, 'store']);

        Route::get('inbox', [InboxController::class, 'index']);
        Route::get('inbox/{envelope}', [InboxController::class, 'show']);
        Route::post('inbox/{envelope}/open', [InboxController::class, 'open']);
        Route::post('inbox/{envelope}/rsvp', [InboxController::class, 'rsvp']);

        Route::get('templates', [TemplateController::class, 'index']);
        Route::post('contacts/match', [ContactController::class, 'match']);

        Route::get('events', [EventController::class, 'index']);
        Route::post('events', [EventController::class, 'store']);
        Route::get('events/{event}', [EventController::class, 'show']);
        Route::patch('events/{event}', [EventController::class, 'update']);
        Route::patch('events/{event}/headcount-sharing', [EventController::class, 'headcountSharing']);

        Route::get('events/{event}/invitation', [InvitationController::class, 'show']);
        Route::put('events/{event}/invitation', [InvitationController::class, 'upsert']);

        Route::get('events/{event}/invitation/recipients', [RecipientController::class, 'index']);
        Route::post('events/{event}/invitation/recipients', [RecipientController::class, 'store']);
        Route::delete('events/{event}/invitation/recipients/{recipient}', [RecipientController::class, 'destroy'])
            ->scopeBindings();
        Route::post('events/{event}/invitation/send', [InvitationController::class, 'send']);
        Route::get('events/{event}/invitation/stats', [InvitationController::class, 'stats']);
    });
});

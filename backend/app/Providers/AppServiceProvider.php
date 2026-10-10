<?php

namespace App\Providers;

use App\Models\SalonUser;
use App\Services\Push\LogNotificationSender;
use App\Services\Push\NotificationSender;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\NetgsmSmsSender;
use App\Services\Sms\SmsSender;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use libphonenumber\PhoneNumberUtil;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PhoneNumberUtil::class, fn () => PhoneNumberUtil::getInstance());

        $this->app->singleton(SmsSender::class, function ($app) {
            // Kodu loga yazan gönderici yalnız local/testing'de; başka ortamda gerçek sağlayıcı.
            if ($app->environment(['local', 'testing'])) {
                return new LogSmsSender;
            }

            return new NetgsmSmsSender(
                config('services.netgsm.usercode'),
                config('services.netgsm.password'),
                config('services.netgsm.header'),
            );
        });

        // FCM gönderici Adım 9'da gelecek; o zamana kadar bildirimler kuyruktan geçip yalnız loglanır.
        $this->app->bind(NotificationSender::class, LogNotificationSender::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        Event::listen(function (Login $event) {
            if ($event->user instanceof SalonUser) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });

        RateLimiter::for('invite-page', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        RateLimiter::for('otp-ip', fn (Request $request) => Limit::perHour(config('zarf.otp.per_ip_per_hour'))->by($request->ip()));
    }
}

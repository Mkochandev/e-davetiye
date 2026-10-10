<?php

namespace App\Providers\Filament;

use App\Filament\Salon\Pages\Auth\SalonLogin;
use App\Models\SalonUser;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class SalonPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('salon')
            ->path('salon')
            ->authGuard('salon')
            ->authPasswordBroker(SalonUser::PASSWORD_BROKER)
            ->login(SalonLogin::class)
            ->passwordReset()
            ->brandName('Zarf Salon')
            ->font('Plus Jakarta Sans')
            ->colors([
                'primary' => Color::hex('#8A1F3D'),
                'gray' => Color::Slate,
            ])
            ->discoverResources(in: app_path('Filament/Salon/Resources'), for: 'App\Filament\Salon\Resources')
            ->discoverPages(in: app_path('Filament/Salon/Pages'), for: 'App\Filament\Salon\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Salon/Widgets'), for: 'App\Filament\Salon\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}

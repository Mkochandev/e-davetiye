<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\Recipient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $weekStart = CarbonImmutable::now('Europe/Istanbul')->startOfWeek()->utc();

        $sent = Recipient::whereNotNull('sent_at')->count();
        $opened = Recipient::whereNotNull('sent_at')->whereNotNull('opened_at')->count();

        return [
            Stat::make('Toplam kullanıcı', User::count()),
            Stat::make('Bu hafta gönderilen zarf', Recipient::where('sent_at', '>=', $weekStart)->count()),
            Stat::make('Açılma oranı', $sent > 0 ? '%'.number_format($opened / $sent * 100, 0) : '—')
                ->description("{$opened} / {$sent} zarf"),
            Stat::make('Satılan paket', Purchase::where('status', PurchaseStatus::Verified)->count()),
        ];
    }
}

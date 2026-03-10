<?php

namespace App\Filament\Widgets;

use App\Models\Courier;
use App\Models\MarketplaceListing;
use App\Models\Order;
use App\Models\PickupRequest;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalUsers    = User::count();
        $newUsersToday = User::whereDate('created_at', today())->count();

        $totalCouriers   = Courier::count();
        $activeCouriers  = Courier::where('status', 'active')->count();

        $pendingPickups   = PickupRequest::whereIn('status', ['searching', 'pending'])->count();
        $completedPickups = PickupRequest::where('status', 'completed')->count();

        $pendingOrders   = Order::whereIn('status', ['pending', 'confirmed'])->count();
        $totalRevenue    = Order::where('status', 'completed')->sum('total_price');

        $activeListings = MarketplaceListing::where('is_active', true)->where('is_sold', false)->count();

        return [
            Stat::make('Total Pengguna', $totalUsers)
                ->description("+{$newUsersToday} hari ini")
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('success')
                ->icon('heroicon-o-users'),

            Stat::make('Kurir Aktif', "{$activeCouriers} / {$totalCouriers}")
                ->description("{$totalCouriers} total kurir terdaftar")
                ->descriptionIcon('heroicon-m-truck')
                ->color('info')
                ->icon('heroicon-o-truck'),

            Stat::make('Pickup Menunggu', $pendingPickups)
                ->description("{$completedPickups} pickup selesai")
                ->descriptionIcon('heroicon-m-archive-box')
                ->color($pendingPickups > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-archive-box'),

            Stat::make('Order Aktif', $pendingOrders)
                ->description('Rp ' . number_format($totalRevenue, 0, ',', '.') . ' total revenue')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($pendingOrders > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-clipboard-document-list'),

            Stat::make('Listing Aktif', $activeListings)
                ->description('Produk tersedia di marketplace')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary')
                ->icon('heroicon-o-shopping-bag'),
        ];
    }
}

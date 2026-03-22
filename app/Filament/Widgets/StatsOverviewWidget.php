<?php

namespace App\Filament\Widgets;

use App\Models\Chapter;
use App\Models\CoinTransaction;
use App\Models\Novel;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalRevenue = CoinTransaction::where('type', 'purchase')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('amount');

        $newUsers = User::where('created_at', '>=', now()->startOfMonth())->count();
        $newUsersLastMonth = User::whereBetween('created_at', [
            now()->subMonth()->startOfMonth(),
            now()->subMonth()->endOfMonth(),
        ])->count();

        $draftChapters = Chapter::where('status', 'draft')->count();

        return [
            Stat::make('Total Novels', Novel::count())
                ->description(Novel::where('status', 'ongoing')->count() . ' ongoing')
                ->descriptionIcon('heroicon-m-book-open')
                ->color('primary')
                ->chart(
                    Novel::selectRaw('count(*) as count')
                        ->where('created_at', '>=', now()->subDays(7))
                        ->groupByRaw('DATE(created_at)')
                        ->pluck('count')
                        ->toArray()
                ),

            Stat::make('New Users (This Month)', number_format($newUsers))
                ->description($newUsersLastMonth > 0
                    ? round(($newUsers - $newUsersLastMonth) / $newUsersLastMonth * 100) . '% vs last month'
                    : 'No comparison')
                ->descriptionIcon($newUsers >= $newUsersLastMonth
                    ? 'heroicon-m-arrow-trending-up'
                    : 'heroicon-m-arrow-trending-down')
                ->color($newUsers >= $newUsersLastMonth ? 'success' : 'danger'),

            Stat::make('Coins Purchased (This Month)', number_format($totalRevenue))
                ->description('coins this month')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('warning'),

            Stat::make('Draft Chapters', $draftChapters)
                ->description('awaiting publication')
                ->descriptionIcon('heroicon-m-document-text')
                ->color($draftChapters > 10 ? 'danger' : 'gray'),
        ];
    }
}

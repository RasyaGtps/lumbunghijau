<?php
// app/Filament/Widgets/StatsOverview.php
namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\User;
use App\Models\Transaction;
use App\Models\WasteCategory;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1; // Urutan tampil di dashboard

    protected function getStats(): array
    {
        return [
            Stat::make('Total Users', User::count())
                ->description('Semua pengguna terdaftar')
                ->descriptionIcon('heroicon-m-users')
                ->color('success')
                ->chart([7, 2, 10, 3, 15, 4, 17]), // Contoh data chart kecil

            Stat::make('Total Kategori Sampah', WasteCategory::count())
                ->description('Jumlah kategori sampah')
                ->descriptionIcon('heroicon-m-squares-2x2')
                ->color('info'),

            Stat::make('Transaksi Pending', Transaction::where('status', 'pending')->count())
                ->description('Transaksi menunggu verifikasi')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Transaksi Selesai', Transaction::where('status', 'verified')->count())
                ->description('Transaksi selesai')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
        ];
    }
}
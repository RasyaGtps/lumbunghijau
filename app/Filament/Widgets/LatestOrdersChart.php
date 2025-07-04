<?php
// app/Filament/Widgets/LatestOrdersChart.php
namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
// ... (use statements lainnya)

class LatestOrdersChart extends ChartWidget
{
    protected static ?string $heading = 'Transaksi Terbaru (7 Hari Terakhir)';
    protected static ?int $sort = 2; // Urutan kedua
    protected static string $color = 'info';

    // Atur agar mengambil 1 kolom dari 2 kolom yang tersedia
    protected int | string | array $columnSpan = 1;

    // ... (isi getData dan getType Anda tetap sama)
    protected function getData(): array
    {
        $data = \App\Models\Transaction::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', \Carbon\Carbon::now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Transaksi Dibuat',
                    'data' => $data->map(fn ($value) => $value->count)->toArray(),
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'borderColor' => 'rgb(54, 162, 235)',
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $data->map(fn ($value) => \Carbon\Carbon::parse($value->date)->format('d M'))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
<?php
// app/Filament/Pages/Dashboard.php
namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    // Atur jumlah kolom untuk layout widget utama di dashboard
    // Anda bisa menggunakan integer (misal 2 atau 3)
    // atau array untuk layout responsif: ['md' => 2, 'lg' => 3]
    // Untuk kasus ini, 2 kolom utama sudah cukup.
    public function getColumns(): int | string | array
    {
        return 2; // Ini akan membuat layout widget menjadi 2 kolom
    }

    // Anda bisa juga mengatur judul halaman dashboard jika mau
    // public function getTitle(): string
    // {
    //     return 'Ringkasan Sistem';
    // }
}
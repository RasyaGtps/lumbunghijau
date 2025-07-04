<?php
// app/Filament/Widgets/RecentUsersTable.php
namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
// ... (use statements lainnya)

class RecentUsersTable extends BaseWidget
{
    protected static ?int $sort = 3; // Urutan ketiga
    protected static ?string $heading = 'Pengguna Baru Terdaftar';

    // Atur agar mengambil 1 kolom dari 2 kolom yang tersedia
    protected int | string | array $columnSpan = 1;

    // ... (isi table method Anda tetap sama)
    public function table(Table $table): Table
    {
        return $table
            ->query(
                \App\Models\User::query()->latest()->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'danger',
                        'collector' => 'success',
                        'user' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->label('Tanggal Daftar')
                    ->sortable(),
            ]);
    }
}
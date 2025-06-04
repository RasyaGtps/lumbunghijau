<?php

namespace App\Providers\Filament\Dashboard;

use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\UserResource;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->pages([
                Dashboard::class,
            ])
            ->resources([
                UserResource::class,
            ])
            ->path('admin');
    }
} 
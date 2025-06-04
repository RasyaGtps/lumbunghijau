<?php

namespace App\Filament\Resources\CollectorResource\Pages;

use App\Filament\Resources\CollectorResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCollectors extends ListRecords
{
    protected static string $resource = CollectorResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

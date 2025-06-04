<?php

namespace App\Filament\Resources\WasteCategoryResource\Pages;

use App\Filament\Resources\WasteCategoryResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Pages\Actions;

class ListWasteCategories extends ListRecords
{
    protected static string $resource = WasteCategoryResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WasteCategoryResource\Pages;
use App\Models\WasteCategory;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;

class WasteCategoryResource extends Resource
{
    protected static ?string $model = WasteCategory::class;
    protected static ?string $navigationIcon = 'heroicon-o-folder';


    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),

            Forms\Components\Select::make('type')
                ->required()
                ->options([
                    'organic' => 'Organic',
                    'inorganic' => 'Inorganic',
                    'hazardous' => 'Hazardous',
                ]),

            Forms\Components\TextInput::make('price_per_kg')
                ->label('Price per Kg')
                ->numeric()
                ->required()
                ->minValue(0)
                ->step(0.01),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('id')->sortable(),
            Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
            Tables\Columns\BadgeColumn::make('type')
                ->state(function ($record): string {
                    return match ($record->type) {
                        'organic' => 'Organic',
                        'inorganic' => 'Inorganic',
                        'hazardous' => 'Hazardous',
                        default => $record->type,
                    };
                })
                ->colors([
                    'success' => 'organic',
                    'warning' => 'inorganic',
                    'danger' => 'hazardous',
                ])
                ->sortable(),
            Tables\Columns\TextColumn::make('price_per_kg')->money('usd', true)->sortable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([
            //
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])->bulkActions([
            Tables\Actions\DeleteBulkAction::make(),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWasteCategories::route('/'),
            'create' => Pages\CreateWasteCategory::route('/create'),
            'edit' => Pages\EditWasteCategory::route('/{record}/edit'),
        ];
    }
}

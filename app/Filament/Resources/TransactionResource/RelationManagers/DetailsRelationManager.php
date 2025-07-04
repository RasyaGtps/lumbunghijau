<?php

namespace App\Filament\Resources\TransactionResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DetailsRelationManager extends RelationManager
{
    protected static string $relationship = 'details';

    protected static ?string $title = 'Detail Transaksi';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('category_id')
                    ->relationship('wasteCategory', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->label('Kategori Sampah'),
                    
                Forms\Components\TextInput::make('estimated_weight')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->label('Berat Estimasi (kg)'),
                    
                Forms\Components\TextInput::make('actual_weight')
                    ->numeric()
                    ->minValue(0)
                    ->nullable()
                    ->label('Berat Aktual (kg)'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('wasteCategory.name')
                    ->sortable()
                    ->searchable()
                    ->label('Kategori Sampah'),
                    
                Tables\Columns\TextColumn::make('estimated_weight')
                    ->suffix(' kg')
                    ->sortable()
                    ->label('Berat Estimasi'),
                    
                Tables\Columns\TextColumn::make('actual_weight')
                    ->suffix(' kg')
                    ->sortable()
                    ->label('Berat Aktual'),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Dibuat pada'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
} 
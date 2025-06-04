<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionDetailResource\Pages;
use App\Models\TransactionDetail;
use App\Models\Transaction;
use App\Models\WasteCategory;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;

class TransactionDetailResource extends Resource
{
    protected static ?string $model = TransactionDetail::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Select::make('transaction_id')
                ->label('Transaction')
                ->required()
                ->relationship('transaction', 'id')
                ->searchable(),

            Forms\Components\Select::make('category_id')
                ->label('Waste Category')
                ->required()
                ->relationship('category', 'name')
                ->searchable(),

            Forms\Components\TextInput::make('estimated_weight')
                ->label('Estimated Weight (kg)')
                ->numeric()
                ->required()
                ->minValue(0)
                ->step(0.01),

            Forms\Components\TextInput::make('actual_weight')
                ->label('Actual Weight (kg)')
                ->numeric()
                ->required()
                ->minValue(0)
                ->step(0.01),

            Forms\Components\FileUpload::make('photo_path')
                ->label('Photo')
                ->image()
                ->directory('transaction-details')
                ->maxSize(1024) // 1MB max
                ->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('id')->sortable(),
            Tables\Columns\TextColumn::make('transaction.id')->label('Transaction ID')->sortable(),
            Tables\Columns\TextColumn::make('category.name')->label('Waste Category')->sortable(),
            Tables\Columns\TextColumn::make('estimated_weight')->label('Estimated Weight (kg)')->sortable(),
            Tables\Columns\TextColumn::make('actual_weight')->label('Actual Weight (kg)')->sortable(),
            Tables\Columns\ImageColumn::make('photo_path')->label('Photo')->disk('public')->rounded(),
            Tables\Columns\TextColumn::make('created_at')->label('Created At')->dateTime()->sortable(),
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
            'index' => Pages\ListTransactionDetails::route('/'),
            'create' => Pages\CreateTransactionDetail::route('/create'),
            'edit' => Pages\EditTransactionDetail::route('/{record}/edit'),
        ];
    }
}

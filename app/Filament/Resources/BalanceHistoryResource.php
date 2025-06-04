<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BalanceHistoryResource\Pages;
use App\Models\BalanceHistory;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Tables;

class BalanceHistoryResource extends Resource
{
    protected static ?string $model = BalanceHistory::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->required(),

                Forms\Components\TextInput::make('amount')
                    ->label('Amount')
                    ->numeric()
                    ->required(),

                Forms\Components\Select::make('type')
                    ->label('Type')
                    ->options([
                        'credit' => 'Credit',
                        'debit' => 'Debit',
                    ])
                    ->required(),

                Forms\Components\Select::make('transaction_id')
                    ->label('Transaction')
                    ->relationship('transaction', 'id'),

                Forms\Components\DateTimePicker::make('timestamp')
                    ->label('Timestamp')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('usd', true),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->sortable(),

                Tables\Columns\TextColumn::make('transaction.id')
                    ->label('Transaction ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('timestamp')
                    ->label('Timestamp')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBalanceHistories::route('/'),
            'create' => Pages\CreateBalanceHistory::route('/create'),
            'edit' => Pages\EditBalanceHistory::route('/{record}/edit'),
        ];
    }
}

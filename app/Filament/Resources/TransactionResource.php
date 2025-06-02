<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Transaction;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')
                ->label('User')
                ->required()
                ->relationship('user', 'name')
                ->searchable(),

            Forms\Components\TextInput::make('pickup_location')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('total_weight')
                ->label('Total Weight (kg)')
                ->numeric()
                ->required()
                ->minValue(0)
                ->step(0.01),

            Forms\Components\TextInput::make('total_price')
                ->label('Total Price')
                ->numeric()
                ->required()
                ->minValue(0)
                ->step(0.01),

            Forms\Components\Select::make('status')
                ->required()
                ->options([
                    'pending' => 'Pending',
                    'verified' => 'Verified',
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                ])
                ->default('pending'),

            Forms\Components\FileUpload::make('qr_code_path')
                ->label('QR Code')
                ->directory('transactions/qr-codes')
                ->nullable(),

            Forms\Components\TextInput::make('verification_token')
                ->label('Verification Token')
                ->maxLength(255)
                ->nullable(),

            Forms\Components\DateTimePicker::make('token_expires_at')
                ->label('Token Expires At')
                ->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('id')->sortable(),
            Tables\Columns\TextColumn::make('user.name')->label('User')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('pickup_location')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('total_weight')->label('Total Weight (kg)')->sortable(),
            Tables\Columns\TextColumn::make('total_price')->label('Total Price')->money('usd', true)->sortable(),
            Tables\Columns\BadgeColumn::make('status')
                ->enum([
                    'pending' => 'Pending',
                    'verified' => 'Verified',
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                ])
                ->colors([
                    'warning' => 'pending',
                    'success' => 'verified',
                    'primary' => 'completed',
                    'danger' => 'cancelled',
                ])
                ->sortable(),
            Tables\Columns\ImageColumn::make('qr_code_path')->label('QR Code')->disk('public')->rounded(),
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
            // Define relations here if needed
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }
}

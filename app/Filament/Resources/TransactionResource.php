<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Resources\TransactionResource\RelationManagers;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Navigation\NavigationItem;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Transactions';
    
    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() ?: null;
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\Select::make('user_id')
                ->relationship('user', 'name')
                ->searchable()
                ->preload()
                    ->required()
                    ->label('Pengguna'),
                    
            Forms\Components\Textarea::make('pickup_location')
                    ->nullable()
                    ->columnSpanFull()
                    ->label('Lokasi Pengambilan'),
                    
            Forms\Components\TextInput::make('total_weight')
                ->numeric()
                ->inputMode('decimal')
                    ->nullable()
                    ->suffix('kg')
                    ->label('Total Berat'),
                    
            Forms\Components\TextInput::make('total_price')
                ->numeric()
                ->inputMode('decimal')
                    ->nullable()
                    ->prefix('Rp')
                    ->label('Total Harga'),
                    
                Forms\Components\FileUpload::make('image_path')
                ->image()
                    ->directory('transaction_proofs')
                    ->nullable()
                    ->label('Bukti Foto'),
                    
                Forms\Components\Select::make('status')
                ->options([
                        'cart' => 'Keranjang',
                        'pending' => 'Menunggu',
                        'verified' => 'Terverifikasi',
                        'rejected' => 'Ditolak',
                ])
                ->required()
                    ->default('cart')
                    ->label('Status'),
                    
            Forms\Components\Textarea::make('rejection_reason')
                ->nullable()
                    ->columnSpanFull()
                    ->visible(fn ($get) => $get('status') === 'rejected')
                    ->label('Alasan Penolakan'),
        ]);
}

public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('user.name')
                    ->label('Pengguna')
                ->searchable()
                ->sortable(),
                    
                Tables\Columns\TextColumn::make('status')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'cart' => 'gray',
                    'pending' => 'warning',
                    'verified' => 'success',
                    'rejected' => 'danger',
                    default => 'gray',
                })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cart' => 'Keranjang',
                        'pending' => 'Menunggu',
                        'verified' => 'Terverifikasi',
                        'rejected' => 'Ditolak',
                        default => $state,
                    })
                ->searchable(),
                    
            Tables\Columns\TextColumn::make('total_weight')
                ->suffix(' kg')
                    ->sortable()
                    ->label('Total Berat'),
                    
            Tables\Columns\TextColumn::make('total_price')
                ->money('IDR')
                    ->sortable()
                    ->label('Total Harga'),
                    
            Tables\Columns\TextColumn::make('pickup_location')
                ->limit(30)
                    ->tooltip(fn ($record) => $record->pickup_location)
                    ->label('Lokasi Pengambilan'),
                    
                Tables\Columns\ImageColumn::make('image_path')
                    ->disk('public')
                    ->label('Bukti Foto'),
                    
                Tables\Columns\TextColumn::make('rejection_reason')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record?->rejection_reason)
                    ->visible(fn ($record) => $record?->status === 'rejected')
                    ->label('Alasan Penolakan'),
                    
            Tables\Columns\TextColumn::make('created_at')
                ->dateTime()
                ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Dibuat pada'),
        ])
            ->filters([
                //
            ])
        ->actions([
                Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ]),
        ]);
}

    public static function getRelations(): array
    {
        return [
            RelationManagers\DetailsRelationManager::class,
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

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WithdrawalResource\Pages;
use App\Filament\Resources\WithdrawalResource\RelationManagers;
use App\Models\Withdrawal;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;

class WithdrawalResource extends Resource
{
    protected static ?string $model = Withdrawal::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Financial';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->inputMode('decimal')
                    ->prefix('IDR'),
                Forms\Components\TextInput::make('method')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., Bank Transfer, DANA, GOPAY'),
                Forms\Components\TextInput::make('virtual_account')
                    ->label('Account Details / VA Number')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., Bank BCA 123456789 an John Doe'),
                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'accepted' => 'Accepted',
                        'rejected' => 'Rejected',
                        'expired' => 'Expired',
                    ])
                    ->required()
                    ->default('pending')
                    ->afterStateUpdated(function ($state, $old, $record, $set) {
                        if (!$record) return; // Skip if this is a new record
                        
                        $user = $record->user;
                        
                        // Jika status berubah menjadi accepted
                        if ($state === 'accepted' && $old !== 'accepted') {
                            // Cek apakah user memiliki saldo yang cukup
                            if ($user->balance < $record->amount) {
                                Notification::make()
                                    ->danger()
                                    ->title('Saldo tidak cukup')
                                    ->body('User tidak memiliki saldo yang cukup untuk withdrawal ini.')
                                    ->send();
                                    
                                $set('status', $old); // Kembalikan ke status sebelumnya
                                return;
                            }
                            
                            // Kurangi saldo user
                            $user->balance -= $record->amount;
                            $user->save();
                            
                            Notification::make()
                                ->success()
                                ->title('Withdrawal berhasil')
                                ->body("Saldo user telah dikurangi sebesar IDR " . number_format($record->amount, 2))
                                ->send();
                        }
                        
                        // Jika status berubah dari accepted ke status lain
                        if ($old === 'accepted' && $state !== 'accepted') {
                            // Kembalikan saldo user
                            $user->balance += $record->amount;
                            $user->save();
                            
                            Notification::make()
                                ->success()
                                ->title('Saldo dikembalikan')
                                ->body("Saldo user telah dikembalikan sebesar IDR " . number_format($record->amount, 2))
                                ->send();
                        }
                    }),
                Forms\Components\DateTimePicker::make('expires_at')
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.balance')
                    ->label('Saldo User')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('method')
                    ->searchable(),
                Tables\Columns\TextColumn::make('virtual_account')
                    ->label('Account Details')
                    ->limit(40)
                    ->tooltip(fn (Withdrawal $record): string => $record->virtual_account),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'accepted' => 'success',
                        'rejected' => 'danger',
                        'expired' => 'gray',
                        default => 'secondary',
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'accepted' => 'Accepted',
                        'rejected' => 'Rejected',
                        'expired' => 'Expired',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
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
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWithdrawals::route('/'),
            'create' => Pages\CreateWithdrawal::route('/create'),
            'edit' => Pages\EditWithdrawal::route('/{record}/edit'),
        ];
    }
}
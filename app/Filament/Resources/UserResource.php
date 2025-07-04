<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';


public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('email')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('phone_number') // BARU
                ->tel()
                ->required() // Sesuai SQL `NOT NULL`
                ->maxLength(255),
            Forms\Components\TextInput::make('password')
                ->password()
                ->revealable()
                ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                ->dehydrated(fn ($state) => filled($state))
                ->required(fn (string $context): bool => $context === 'create'),
            Forms\Components\FileUpload::make('avatar') // BARU
                ->image()
                ->directory('avatars') // Tentukan folder penyimpanan di storage/app/public
                ->nullable(),
            Forms\Components\Select::make('role') // ENUM
                ->options([
                    'user' => 'User',
                    'collector' => 'Collector',
                    'admin' => 'Admin',
                ])
                ->required()
                ->default('user'),
            Forms\Components\TextInput::make('balance')
                ->numeric()
                ->inputMode('decimal')
                ->required()
                ->default(0.00),
            Forms\Components\Toggle::make('email_verified') // BARU (boolean)
                ->label('Email Verified')
                ->default(false),
            Forms\Components\TextInput::make('otp_code') // BARU
                ->nullable()
                ->maxLength(255),
            Forms\Components\DateTimePicker::make('otp_expires_at') // BARU
                ->nullable(),
        ]);
}
public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\ImageColumn::make('avatar') // BARU
                ->disk('public') // Jika disimpan di storage/app/public/avatars
                ->circular(), // Opsional: membuat gambar avatar bundar
            Tables\Columns\TextColumn::make('name')
                ->searchable(),
            Tables\Columns\TextColumn::make('email')
                ->searchable(),
            Tables\Columns\TextColumn::make('phone_number') // BARU
                ->searchable(),
            Tables\Columns\TextColumn::make('role') // ENUM
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'admin' => 'danger',
                    'collector' => 'success',
                    'user' => 'info',
                    default => 'gray',
                })
                ->searchable(),
            Tables\Columns\TextColumn::make('balance')
                ->money('IDR') // Atau mata uang lain
                ->sortable(),
            Tables\Columns\IconColumn::make('email_verified') // BARU (boolean)
                ->label('Verified')
                ->boolean(),
            Tables\Columns\TextColumn::make('created_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('updated_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ])
        // ... (filters, actions, bulkActions tetap sama atau sesuaikan jika perlu)
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}

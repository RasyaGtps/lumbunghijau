<?php
// app/Filament/Resources/TransactionResource/RelationManagers/TransactionDetailsRelationManager.php
namespace App\Filament\Resources\TransactionResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\FileUpload; // Untuk upload foto
use App\Models\WasteCategory; // Untuk select category

class TransactionDetailsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactionDetails';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('category_id')
                    ->label('Waste Category')
                    ->options(WasteCategory::pluck('name', 'id')) // Ambil dari WasteCategory model
                    ->searchable()
                    ->required()
                    // Untuk mengisi price_per_kg_at_transaction secara otomatis saat kategori dipilih
                    ->reactive()
                    ->afterStateUpdated(function (Forms\Set $set, $state) {
                        $category = WasteCategory::find($state);
                        if ($category) {
                            $set('price_per_kg_at_transaction', $category->price_per_kg);
                        }
                    }),
                Forms\Components\TextInput::make('estimated_weight')
                    ->required()
                    ->numeric()
                    ->inputMode('decimal')
                    ->suffix('kg'),
                Forms\Components\TextInput::make('actual_weight')
                    ->numeric()
                    ->inputMode('decimal')
                    ->nullable()
                    ->suffix('kg'),
                Forms\Components\TextInput::make('price_per_kg_at_transaction')
                    ->numeric()
                    ->inputMode('decimal')
                    ->label('Price/kg (at transaction)')
                    ->nullable()
                    ->prefix('IDR'),
                FileUpload::make('photo_path')
                    ->label('Photo')
                    ->directory('transaction_photos') // Folder penyimpanan di storage/app/public
                    ->image() // Validasi sebagai gambar
                    ->nullable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('category_id') // Atau pilih atribut yang lebih deskriptif jika ada
            ->columns([
                Tables\Columns\TextColumn::make('wasteCategory.name') // Tampilkan nama kategori
                    ->label('Category'),
                Tables\Columns\TextColumn::make('estimated_weight')
                    ->suffix(' kg'),
                Tables\Columns\TextColumn::make('actual_weight')
                    ->suffix(' kg')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('price_per_kg_at_transaction')
                    ->money('IDR')
                    ->placeholder('-'),
                Tables\Columns\ImageColumn::make('photo_path') // Tampilkan gambar
                    ->label('Photo')
                    ->disk('public'), // Pastikan disk 'public'
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
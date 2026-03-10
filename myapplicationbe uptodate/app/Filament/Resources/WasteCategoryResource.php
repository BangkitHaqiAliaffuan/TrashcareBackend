<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WasteCategoryResource\Pages;
use App\Models\WasteCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WasteCategoryResource extends Resource
{
    protected static ?string $model = WasteCategory::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Konfigurasi';
    protected static ?string $navigationLabel = 'Kategori Sampah';
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Detail Kategori')->schema([
                Forms\Components\TextInput::make('type')
                    ->label('Tipe (slug)')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->helperText('Contoh: organic, plastic, electronic, glass')
                    ->maxLength(50),

                Forms\Components\TextInput::make('label')
                    ->label('Label')
                    ->required()
                    ->maxLength(100),

                Forms\Components\TextInput::make('emoji')
                    ->label('Emoji')
                    ->maxLength(10)
                    ->placeholder('♻️'),

                Forms\Components\TextInput::make('points_per_kg')
                    ->label('Poin per Kg')
                    ->numeric()
                    ->default(10)
                    ->required(),

                Forms\Components\Textarea::make('description')
                    ->label('Deskripsi')
                    ->nullable()
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('emoji')
                    ->label('')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('label')
                    ->label('Label')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('points_per_kg')
                    ->label('Poin/Kg')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('success'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('pickupItems_count')
                    ->label('Total Digunakan')
                    ->counts('pickupItems')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif'),
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

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListWasteCategories::route('/'),
            'create' => Pages\CreateWasteCategory::route('/create'),
            'edit'   => Pages\EditWasteCategory::route('/{record}/edit'),
        ];
    }
}

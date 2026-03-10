<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MarketplaceListingResource\Pages;
use App\Models\MarketplaceListing;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MarketplaceListingResource extends Resource
{
    protected static ?string $model = MarketplaceListing::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'Operasional';
    protected static ?string $navigationLabel = 'Marketplace';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Detail Produk')->schema([
                Forms\Components\Select::make('seller_id')
                    ->label('Penjual')
                    ->relationship('seller', 'name')
                    ->searchable()
                    ->required(),

                Forms\Components\TextInput::make('name')
                    ->label('Nama Produk')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Textarea::make('description')
                    ->label('Deskripsi')
                    ->nullable()
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('price')
                    ->label('Harga (Rp)')
                    ->numeric()
                    ->required()
                    ->prefix('Rp'),

                Forms\Components\Select::make('category')
                    ->label('Kategori')
                    ->options([
                        'organic'    => 'Organik',
                        'plastic'    => 'Plastik',
                        'electronic' => 'Elektronik',
                        'glass'      => 'Kaca',
                        'paper'      => 'Kertas',
                        'metal'      => 'Logam',
                        'other'      => 'Lainnya',
                    ])
                    ->required(),

                Forms\Components\Select::make('condition')
                    ->label('Kondisi')
                    ->options([
                        'new'       => 'Baru',
                        'like_new'  => 'Seperti Baru',
                        'good'      => 'Bagus',
                        'fair'      => 'Cukup',
                        'poor'      => 'Kurang Baik',
                    ])
                    ->required(),
            ])->columns(2),

            Forms\Components\Section::make('Status')->schema([
                Forms\Components\Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),

                Forms\Components\Toggle::make('is_sold')
                    ->label('Sudah Terjual')
                    ->default(false),
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

                Tables\Columns\TextColumn::make('name')
                    ->label('Produk')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('seller.name')
                    ->label('Penjual')
                    ->searchable(),

                Tables\Columns\TextColumn::make('price')
                    ->label('Harga')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('category')
                    ->label('Kategori')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'organic'    => 'Organik',
                        'plastic'    => 'Plastik',
                        'electronic' => 'Elektronik',
                        'glass'      => 'Kaca',
                        'paper'      => 'Kertas',
                        'metal'      => 'Logam',
                        default      => 'Lainnya',
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('is_sold')
                    ->label('Terjual')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('views_count')
                    ->label('Dilihat')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Kategori')
                    ->options([
                        'organic'    => 'Organik',
                        'plastic'    => 'Plastik',
                        'electronic' => 'Elektronik',
                        'glass'      => 'Kaca',
                        'paper'      => 'Kertas',
                        'metal'      => 'Logam',
                        'other'      => 'Lainnya',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif'),

                Tables\Filters\TernaryFilter::make('is_sold')
                    ->label('Status Terjual'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                // Toggle aktif/nonaktif listing
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn ($record) => $record->is_active ? 'Nonaktifkan' : 'Aktifkan')
                    ->icon(fn ($record) => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn ($record) => $record->is_active ? 'danger' : 'success')
                    ->action(fn ($record) => $record->update(['is_active' => ! $record->is_active]))
                    ->requiresConfirmation(),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMarketplaceListings::route('/'),
            'create' => Pages\CreateMarketplaceListing::route('/create'),
            'edit'   => Pages\EditMarketplaceListing::route('/{record}/edit'),
            'view'   => Pages\ViewMarketplaceListing::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_active', true)->where('is_sold', false)->count() ?: null;
    }
}

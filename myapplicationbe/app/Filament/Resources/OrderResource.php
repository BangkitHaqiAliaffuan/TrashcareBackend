<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Operasional';
    protected static ?string $navigationLabel = 'Order';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informasi Order')->schema([
                Forms\Components\Select::make('buyer_id')
                    ->label('Pembeli')
                    ->relationship('buyer', 'name')
                    ->searchable()
                    ->required(),

                Forms\Components\Select::make('listing_id')
                    ->label('Produk')
                    ->relationship('listing', 'name')
                    ->searchable()
                    ->required(),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'pending'   => 'Menunggu',
                        'confirmed' => 'Dikonfirmasi',
                        'shipped'   => 'Dikirim',
                        'completed' => 'Selesai',
                        'cancelled' => 'Dibatalkan',
                    ])
                    ->required(),

                Forms\Components\TextInput::make('quantity')
                    ->label('Jumlah')
                    ->numeric()
                    ->default(1)
                    ->required(),

                Forms\Components\TextInput::make('total_price')
                    ->label('Total Harga (Rp)')
                    ->numeric()
                    ->required()
                    ->prefix('Rp'),

                Forms\Components\Textarea::make('shipping_address')
                    ->label('Alamat Pengiriman')
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('notes')
                    ->label('Catatan')
                    ->nullable()
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('cancellation_reason')
                    ->label('Alasan Pembatalan')
                    ->nullable()
                    ->columnSpanFull(),
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

                Tables\Columns\TextColumn::make('buyer.name')
                    ->label('Pembeli')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('listing.name')
                    ->label('Produk')
                    ->limit(35)
                    ->searchable(),

                Tables\Columns\TextColumn::make('total_price')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'info'    => 'confirmed',
                        'primary' => 'shipped',
                        'success' => 'completed',
                        'danger'  => 'cancelled',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pending'   => 'Menunggu',
                        'confirmed' => 'Dikonfirmasi',
                        'shipped'   => 'Dikirim',
                        'completed' => 'Selesai',
                        'cancelled' => 'Dibatalkan',
                        default     => $state,
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending'   => 'Menunggu',
                        'confirmed' => 'Dikonfirmasi',
                        'shipped'   => 'Dikirim',
                        'completed' => 'Selesai',
                        'cancelled' => 'Dibatalkan',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                // Konfirmasi order
                Tables\Actions\Action::make('confirm')
                    ->label('Konfirmasi')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->action(fn ($record) => $record->update([
                        'status'       => 'confirmed',
                        'confirmed_at' => now(),
                    ]))
                    ->requiresConfirmation(),

                // Tandai sebagai dikirim
                Tables\Actions\Action::make('ship')
                    ->label('Kirim')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn ($record) => $record->status === 'confirmed')
                    ->action(fn ($record) => $record->update([
                        'status'    => 'shipped',
                        'shipped_at'=> now(),
                    ]))
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
            'index'  => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit'   => Pages\EditOrder::route('/{record}/edit'),
            'view'   => Pages\ViewOrder::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereIn('status', ['pending', 'confirmed'])->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}

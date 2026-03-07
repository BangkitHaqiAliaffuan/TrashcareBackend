<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CourierResource\Pages;
use App\Models\Courier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class CourierResource extends Resource
{
    protected static ?string $model = Courier::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Manajemen Pengguna';
    protected static ?string $navigationLabel = 'Kurir';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informasi Kurir')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),

                Forms\Components\TextInput::make('phone')
                    ->label('No. Telepon')
                    ->tel()
                    ->maxLength(20),

                Forms\Components\TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation) => $operation === 'create'),
            ])->columns(2),

            Forms\Components\Section::make('Kendaraan & Status')->schema([
                Forms\Components\TextInput::make('vehicle_type')
                    ->label('Jenis Kendaraan')
                    ->placeholder('motor / mobil'),

                Forms\Components\TextInput::make('vehicle_plate')
                    ->label('Plat Nomor')
                    ->maxLength(20),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'active'   => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                        'on_duty'  => 'Sedang Bertugas',
                    ])
                    ->default('active')
                    ->required(),

                Forms\Components\Toggle::make('is_available')
                    ->label('Tersedia')
                    ->default(true),
            ])->columns(2),

            Forms\Components\Section::make('Performa')->schema([
                Forms\Components\TextInput::make('rating')
                    ->label('Rating')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(5)
                    ->default(0),

                Forms\Components\TextInput::make('total_deliveries')
                    ->label('Total Pengiriman')
                    ->numeric()
                    ->default(0),
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
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),

                Tables\Columns\TextColumn::make('vehicle_type')
                    ->label('Kendaraan')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('vehicle_plate')
                    ->label('Plat')
                    ->placeholder('—'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'on_duty',
                        'danger'  => 'inactive',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'active'   => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                        'on_duty'  => 'Bertugas',
                        default    => $state,
                    }),

                Tables\Columns\IconColumn::make('is_available')
                    ->label('Tersedia')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('rating')
                    ->label('Rating')
                    ->formatStateUsing(fn ($state) => number_format($state, 1) . ' ⭐')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_deliveries')
                    ->label('Pengiriman')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active'   => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                        'on_duty'  => 'Bertugas',
                    ]),

                Tables\Filters\TernaryFilter::make('is_available')
                    ->label('Ketersediaan'),
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
            'index'  => Pages\ListCouriers::route('/'),
            'create' => Pages\CreateCourier::route('/create'),
            'edit'   => Pages\EditCourier::route('/{record}/edit'),
            'view'   => Pages\ViewCourier::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}

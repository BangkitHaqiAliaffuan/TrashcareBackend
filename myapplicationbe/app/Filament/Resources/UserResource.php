<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Manajemen Pengguna';
    protected static ?string $navigationLabel = 'Pengguna';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informasi Akun')->schema([
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

            Forms\Components\Section::make('Statistik')->schema([
                Forms\Components\TextInput::make('total_pickups')
                    ->label('Total Pickup')
                    ->numeric()
                    ->default(0),

                Forms\Components\TextInput::make('items_sold')
                    ->label('Item Terjual')
                    ->numeric()
                    ->default(0),

                Forms\Components\TextInput::make('points_balance')
                    ->label('Saldo Poin')
                    ->numeric()
                    ->default(0),

                Forms\Components\TextInput::make('co2_saved')
                    ->label('CO₂ Saved (kg)')
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
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Telepon')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('total_pickups')
                    ->label('Pickup')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('items_sold')
                    ->label('Terjual')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('points_balance')
                    ->label('Poin')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Bergabung')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_pickups')
                    ->label('Punya Pickup')
                    ->query(fn ($query) => $query->where('total_pickups', '>', 0)),

                Tables\Filters\Filter::make('has_points')
                    ->label('Punya Poin')
                    ->query(fn ($query) => $query->where('points_balance', '>', 0)),
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
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
            'view'   => Pages\ViewUser::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}

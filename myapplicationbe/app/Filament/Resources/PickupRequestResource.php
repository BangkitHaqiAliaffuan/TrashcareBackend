<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PickupRequestResource\Pages;
use App\Models\Courier;
use App\Models\PickupRequest;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PickupRequestResource extends Resource
{
    protected static ?string $model = PickupRequest::class;
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationGroup = 'Operasional';
    protected static ?string $navigationLabel = 'Pickup Request';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informasi Pickup')->schema([
                Forms\Components\Select::make('user_id')
                    ->label('Pengguna')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->required(),

                Forms\Components\Select::make('courier_id')
                    ->label('Kurir')
                    ->relationship('courier', 'name')
                    ->searchable()
                    ->nullable(),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'searching' => 'Mencari Kurir',
                        'pending'   => 'Dikonfirmasi',
                        'on_the_way'=> 'Dalam Perjalanan',
                        'completed' => 'Selesai',
                        'cancelled' => 'Dibatalkan',
                    ])
                    ->required(),

                Forms\Components\Textarea::make('address')
                    ->label('Alamat')
                    ->required()
                    ->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('Jadwal & Detail')->schema([
                Forms\Components\DatePicker::make('pickup_date')
                    ->label('Tanggal Pickup')
                    ->required(),

                Forms\Components\TimePicker::make('pickup_time')
                    ->label('Jam Pickup')
                    ->required(),

                Forms\Components\TextInput::make('estimated_weight_kg')
                    ->label('Estimasi Berat (kg)')
                    ->numeric()
                    ->nullable(),

                Forms\Components\TextInput::make('points_awarded')
                    ->label('Poin Diberikan')
                    ->numeric()
                    ->nullable(),

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

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Pengguna')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('courier.name')
                    ->label('Kurir')
                    ->placeholder('Belum assigned')
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray'    => 'searching',
                        'warning' => 'pending',
                        'info'    => 'on_the_way',
                        'success' => 'completed',
                        'danger'  => 'cancelled',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'searching'  => 'Mencari Kurir',
                        'pending'    => 'Dikonfirmasi',
                        'on_the_way' => 'Dalam Perjalanan',
                        'completed'  => 'Selesai',
                        'cancelled'  => 'Dibatalkan',
                        default      => $state,
                    }),

                Tables\Columns\TextColumn::make('pickup_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('pickup_time')
                    ->label('Jam'),

                Tables\Columns\TextColumn::make('estimated_weight_kg')
                    ->label('Berat (kg)')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('points_awarded')
                    ->label('Poin')
                    ->placeholder('—')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'searching'  => 'Mencari Kurir',
                        'pending'    => 'Dikonfirmasi',
                        'on_the_way' => 'Dalam Perjalanan',
                        'completed'  => 'Selesai',
                        'cancelled'  => 'Dibatalkan',
                    ]),

                Tables\Filters\Filter::make('unassigned')
                    ->label('Belum Ada Kurir')
                    ->query(fn ($query) => $query->whereNull('courier_id')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                // Assign kurir langsung dari tabel
                Tables\Actions\Action::make('assign_courier')
                    ->label('Assign Kurir')
                    ->icon('heroicon-o-user-plus')
                    ->color('info')
                    ->visible(fn ($record) => in_array($record->status, ['searching', 'pending']))
                    ->form([
                        Forms\Components\Select::make('courier_id')
                            ->label('Pilih Kurir')
                            ->options(
                                Courier::where('status', 'active')
                                    ->where('is_available', true)
                                    ->pluck('name', 'id')
                            )
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'courier_id' => $data['courier_id'],
                            'status'     => 'pending',
                        ]);
                    }),

                // Selesaikan pickup dan berikan poin
                Tables\Actions\Action::make('complete')
                    ->label('Selesaikan')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'on_the_way')
                    ->form([
                        Forms\Components\TextInput::make('points_awarded')
                            ->label('Poin Diberikan')
                            ->numeric()
                            ->required()
                            ->default(50),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'status'         => 'completed',
                            'points_awarded' => $data['points_awarded'],
                            'completed_at'   => now(),
                        ]);
                        // Tambah poin ke user
                        $record->user->increment('points_balance', $data['points_awarded']);
                        $record->user->increment('total_pickups');
                    })
                    ->requiresConfirmation(),
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
            'index'  => Pages\ListPickupRequests::route('/'),
            'create' => Pages\CreatePickupRequest::route('/create'),
            'edit'   => Pages\EditPickupRequest::route('/{record}/edit'),
            'view'   => Pages\ViewPickupRequest::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereIn('status', ['searching', 'pending'])->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
